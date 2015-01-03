<?php

/**
* Copyright (c) 2015, Leon Sorokin
* All rights reserved. (MIT Licensed)
*
* Route66.php - PHP micro-router
*/

class Route66 {
	static $base	= '';
	static $routes	= [];
	static $names	= [];
	static $cache	= [];
	static $nomatch	= null;
	static $rxalias	= [
		':all'		=> '.*',
		':seg'		=> '[^/]+',
		':slug'		=> '[a-z0-9-]+',
		':slug2'	=> '[\w-]+',			// slug + underscores
		':num'		=> '[0-9]+',
		':alpha'	=> '[A-Za-z]+',
		':alnum'	=> '[0-9A-Za-z]+',
	];

	// add_route
	public static function __callStatic($meths, $args) {
		$args[0] = self::$base . $args[0];

		// detect var_regs vs route_name
		$regs = null;
		$name = null;

		switch (count($args)) {
			case 3:
				$regs = is_array($args[2])  ? $args[2] : null;
				$name = is_string($args[2]) ? $args[2] : null;
				break;
			case 4:
				$regs = is_array($args[2])  ? $args[2] : $args[3];
				$name = is_string($args[2]) ? $args[2] : $args[3];
				break;
		}

		$hash = self::hash($args[0], json_encode($regs));

		if ($name !== null)
			self::$names[$name] = $hash;

		if (isset(self::$cache[$hash]))
			$args[0] = self::$cache[$hash];
		else {
			// detect and process named params & add'l sugar (unescaped   @foo, @foo:bar, :bar)
			$sweet = '#(?<!\\\\)@(\w+)(:\w+)?|(?<![\\?])(:\w+)#i';

			if (preg_match($sweet, $args[0])) {
				$args[0] = str_replace(['(',')'], ['(?:', ')?'], $args[0]);		// todo: ignore (?

				$args[0] = preg_replace_callback($sweet, function($match) use ($regs) {
					$var = $match[1];
					$typ = isset($match[2]) && $match[2] !== '' ? $match[2] : ( isset($match[3]) ? $match[3] : ':seg' );

					$rex = null;

					if (isset($regs[$var])) {
						if ($regs[$var]{0} == ':')
							$typ = $regs[$var];
						else
							$rex = $regs[$var];
					}

					if (!$rex && $typ) {
						if (!isset(self::$rxalias[$typ]))
							trigger_error("Unknown ':{$typ}' regex alias; '@{$var}' param will not be validated.");
						else
							$rex = self::$rxalias[$typ];
					}

					return '(' . $rex . ')';

				}, $args[0]);
			}

			self::$cache[$hash] = $args[0];
		}

		foreach (explode('|', strtoupper($meths)) as $meth) {
			if (!isset(self::$routes[$meth]))
				self::$routes[$meth] = [];

			self::$routes[$meth][$args[0]] = $args[1];
		}
	}

	protected static function hash($uri, $params) {
		return hash('md5', $uri . ' ' . json_encode($params));
	}

	// set route prefix
	public static function any($route, $callback, $regs = null) {
		self::match('get|post|put|patch|delete|head|options', $route, $callback, $regs);
	}

	// set route prefix
	public static function base($base) {
		self::$base = $base;
	}

	// add_route (multi-method)
	public static function match($meths, $route, $callback, $regs = null) {
		self::__callStatic($meths, [$route, $callback, $regs]);
	}

	// catchall
	public static function error($callback) {
		self::$nomatch = $callback;
	}

	// match_route
	protected static function find($meth, $uri, $from_route = null) {
		$meth = strtoupper($meth);
		// named route?
		if (strpos($uri, '/') === false) {
			if ($hash = @self::$names[$uri])
				$uri = self::$cache[$hash];
		}

		if (!isset(self::$routes[$meth]))
			return false;

		$rset = self::$routes[$meth];

		$do_try = $from_route === null;

		if ($do_try && isset($rset[$uri]))
			return [$rset[$uri], [], $uri];

		foreach ($rset as $route => $func) {
			if ($do_try) {
				if (preg_match('#^' . $route . '$#', $uri, $params)) {
					array_shift($params);
					return [$func, $params, $route];
				}
			}
			else if ($route === $from_route)
				$do_try = true;
		}

		return false;
	}

	public static function dispatch($meth = null, $uri = null, $params = []) {
		$meth = $meth === null ? $_SERVER['REQUEST_METHOD'] : $meth;
		$uri = $uri === null ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : $uri;

		$found = false;
		$from_route = null;

		while ($route = self::find($meth, $uri, $from_route)) {
			$found = true;

			$res = call_user_func_array($route[0], empty($params) ? $route[1] : $params);

			// pass-thru now, maybe waterfall later
			if ($res === true)
				$from_route = $route[2];
			else {
				if (isset($res))
					echo $res;

				break;
			}
		}

		if ($found)
			return;

		if ($nomatch = self::$nomatch) {
			$nomatch($meth, $uri);
			return;
		}

		// default nomatch
		header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
		exit('404 Not Found.');
	}

	public static function is_ajax() {
		return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
	}

	public static function is_https() {
		return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
	}

	public static function redirect($loc, $code = 301) {
		header("Location: $loc", true, $code);
		exit();
	}

	public static function export() {
		return [self::$cache, self::$names];
	}

	public static function import(Array $cfg) {
		self::$cache = $cfg[0];
		self::$names = $cfg[1];
	}
}