Route66.php
-----------
PHP micro-router _(MIT Licensed)_

- Concise, simple syntax wrapping a full feature set
- Easy install; Composer or single file include (~200 SLOC)
- Low routing overhead (< 1ms)*

Route66 was written to add [some features](#added-feats) to the minimalist [Macaw](https://github.com/NoahBuscher/Macaw) router without bloating it.

#### Nginx config example

```nginx
server {
	index index.html index.htm index.php;

	location / {
		try_files $uri $uri/ /index.php?/$uri;
	}

	location ~ \.php$ {
		fastcgi_pass 127.0.0.1:9000;
		fastcgi_index index.php;
		fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
		include fastcgi_params;
	}
}
```

#### Install (require or Composer)

```php
require 'Route66.php';
```

```json
{
	"require": {
		"leeoniya/Route66": "dev-master"
	}
}
```

#### Basic example

```php
// front controller (index.php)
require 'Route66.php';

use Route66 as R;

R::get('/', function() {
	echo 'Hello world!';
});

R::dispatch();
```

#### HTTP methods

```php
// any HTTP method can be bound via static invocation using its name
R::post('/topics', function() {});

// multiple methods
R::match('post|put', '/comment', function() {});

// equivalent to R::match('get|post|put|patch|delete|head|options',..)
R::any('/', function() {};)
```

#### Named params & validation

```php
// basic param (all characters except '/')
R::get('/posts/@id',       function($id) {});

// param validated by regex alias
R::get('/posts/@id:alpha', function($id) {});

// aliases and regexs can be broken out for readability
R::get('/posts/@id',       function($id) {}, ['id' => ':alpha']);
R::get('/posts/@id',       function($id) {}, ['id' => '\w{12}']);

// define a custom alias
R::$rxalias[':date'] = '[0-9]{4}-[0-9]{2}-[0-9]{2}';
```

#### Optional segments & params

```php
// optional params with defaults (set in handler)
R::get('/posts(/@year(/@month(/@day)))', function($year = 2015, $month = 6, $day = 15) {});

// regex alias without a param (non-capturing)
R::get('/posts(/:slug)', function() {});

// optional trailing slash (though rtrim-ing it from REQUEST_URI before dispatch is faster)
R::get('/blog/?', function() {});
```

#### Un-named params
```php
// un-named params via regex capture groups
R::get('/posts/(\w{12})', function($id) {});
```

#### Route prefixing (base path)

```php
R::base('/blog');
R::get('/posts', function() {});	// maps to /blog/posts

R::base('/admin');
R::get('/login', function() {});	// maps to /admin/login
```

#### Before pre-filter (fall-through)

```php
// before all /admin* routes
R::get('/admin(/:all)', function() {
	// verify valid session, etc...
	return true;		// fall through to addtional routes
});

R::get('/admin/dashboard', function() {
	// show dashboard...
});
```

#### Custom catch-all (no route found)

```php
R::error(function($meth, $uri) {
	header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
	exit('404 Not Found.');
});
```

#### Dispatch w/custom URI

```php
R::dispatch('get', '/blog');
```

#### Route caching

```php
// example of storing compiled routes in session
session_start();

if ($routes = @$_SESSION['routes'])
	R::import($routes);

// define all routes here

R::dispatch();

if (!isset($_SESSION['routes']))
	$_SESSION['routes'] = R::export();
```

#### Named routes & reverse routing

```php
// un-named
R::get('/users/@id', function($id) {}, ['id'=>':alpha']);

// named (the trailing 2 args can be passed in any order. all below are equivalent.)
R::get('/users/@id', function($id) {}, 'named1');
R::get('/users/@id', function($id) {}, ['id'=>':alpha'], 'named1');
R::get('/users/@id', function($id) {}, 'named1', ['id'=>':alpha']);

// dispatch a named route
R::dispatch('get', 'named1', ['id' => 'abc123']);
```

#### Util methods

```php
R::is_ajax();
R::is_https();
R::redirect($location, $code = 301);
```

#### Features added to Macaw <a id="added-feats"></a>

- Named params (e.g. `@user:alpha`)
- Optional route segments and params, param defaults
- Route-group prefixing
- Param regex & aliases can be broken out for readability
- Short defs for multi-method routes
- Named routes and reverse routing, custom dispatch
- Per-route fall-through control (Macaw has only global)
- Route compilation cache, import/export
- Utility methods for `is_ajax`, `is_https` and `redirect`
- Modified and added regex aliases

#### Other PHP routers

- Macaw - https://github.com/NoahBuscher/Macaw
- Phroute - https://github.com/mrjgreen/phroute
- Klein - https://github.com/chriso/klein.php
- FastRoute - https://github.com/nikic/FastRoute
- Pux - https://github.com/c9s/Pux
- AltoRouter - https://github.com/dannyvankooten/AltoRouter
- Aura - https://github.com/auraphp/Aura.Router
- mu - https://github.com/lastguest/mu

\* As with any router, speed will depend on: route quantity & complexity, numbers of params, whether caching is used, server hardware, PHP version, opcode caching.