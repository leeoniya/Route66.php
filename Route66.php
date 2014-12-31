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
	static $nomatch	= null;
	static $rxalias	= [
		'num'	=> '[0-9]+',
		'alpha'	=> '[A-Za-z]+',
		'alnum'	=> '[0-9A-Za-z]+',
		'word'	=> '\w+',
		'slug'	=> '[\w-]+',
	];

	// add_route
	public static function __callStatic($meth, $args) {
		$args[0] = self::$base . $args[0];

		if (strpos($args[0], '@') !== false) {
			// param -> regex maps
			$regs = isset($args[2]) && is_array($args[2]) ? $args[2] : [];

			$args[0] = str_replace(['(',')'], ['(?:', ')?'], $args[0]);

			$args[0] = preg_replace_callback('#@([a-z][0-9a-z_:]*)#i', function($match) use (&$regs) {
				@list($var, $typ) = explode(':', $match[1]);

				$rex = null;

				if (isset($regs[$var])) {
					if ($regs[$var]{0} == ':')
						$typ = substr($regs[$var], 1);
					else
						$rex = $regs[$var];
				}

				if (!$rex && $typ) {
					if (!isset(self::$rxalias[$typ]))
						trigger_error("Unknown ':{$typ}' regex alias; '@{$var}' param will not be validated.");
					else
						$rex = self::$rxalias[$typ];
				}

				return $rex ? '(' . $rex . ')' : '([\w-~]+)';

			}, $args[0]);
		}

		$meth = strtoupper($meth);

		if (!isset(self::$routes[$meth]))
			self::$routes[$meth] = [];

		self::$routes[$meth][$args[0]] = $args[1];
	}

	// set route prefix
	public static function any($route, $callback, $regs = null) {
		self::match('get|post|put|delete|head|options', $route, $callback, $regs);
	}

	// set route prefix
	public static function base($base) {
		self::$base = $base;
	}

	// add_route (multi-method)
	public static function match($meths, $route, $callback, $regs = null) {
		foreach (explode('|', $meths) as $meth)
			self::$meth($route, $callback, $regs);
	}

	// catchall
	public static function error($callback) {
		self::$nomatch = $callback;
	}

	// match_route
	protected static function find(&$type, $meth, $uri, $from_route = null) {
		if (!isset($type[$meth]))
			return false;

		$rset = $type[$meth];

		$do_try = $from_route === null;

		if ($do_try && isset($rset[$uri]))
			return [$rset[$uri], [], $uri];

		foreach ($rset as $route => $func) {
			if ($do_try) {
				if (preg_match('#^' . $route . '$#', $uri, $matches))
					return [$func, array_slice($matches, 1), $route];
			}
			else if ($route === $from_route)
				$do_try = true;
		}

		return false;
	}

	public static function dispatch($meth = null, $uri = null) {
		$meth = $meth === null ? $_SERVER['REQUEST_METHOD'] : $meth;
		$uri = $uri === null ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : $uri;

		$found = false;
		$from_route = null;

		while ($route = self::find(self::$routes, $meth, $uri, $from_route)) {
			$found = true;

			$res = call_user_func_array($route[0], $route[1]);

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
}