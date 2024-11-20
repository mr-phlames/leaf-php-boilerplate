<?php

declare(strict_types=1);

namespace Leaf;

/**
 * Leaf Router
 * ---------------
 * Super simple and powerful routing with Leaf
 *
 * @author Michael Darko
 * @since 1.2.0
 * @version 3.0
 */
class Router
{
    /**
     * Callable to be invoked if no matching routes are found
     */
    protected static $notFoundHandler;

    /**
     * Callable to be invoked if app is down
     */
    protected static $downHandler;

    /**
     * 'Middleware' to run at specific times
     */
    protected static $hooks = [
        'router.before' => false,
        'router.before.route' => false,
        'router.after.route' => false,
        'router.after' => false,
    ];

    /**
     * All middleware that should be run
     */
    protected static $middleware = [];

    /**
     * Named middleware
     */
    protected static $namedMiddleware = [];

    /**
     * All added routes and their handlers
     */
    protected static $routes = [];

    /**
     * Sorted list of routes and their handlers
     */
    protected static $appRoutes = [];

    /**
     * All named routes
     */
    protected static $namedRoutes = [];

    /**
     * Route based middleware
     */
    protected static $routeGroupMiddleware = [];

    /**
     * Current group base path
     */
    protected static $groupRoute = '';

    /**
     * Default controller namespace
     */
    protected static $namespace = '';

    /**
     * The Server Base Path for Router Execution
     */
    protected static $serverBasePath = '';

    /**
     * Set the 404 handling function.
     *
     * @param object|callable $handler The function to be executed
     */
    public static function set404($handler = null)
    {
        if (is_callable($handler)) {
            static::$notFoundHandler = $handler;
        } else {
            static::$notFoundHandler = function () {
                \Leaf\Exception\General::default404();
            };
        }
    }

    /**
     * Set a custom maintenance mode callback.
     *
     * @param callable|null $handler The function to be executed
     */
    public static function setDown(?callable $handler = null)
    {
        static::$downHandler = $handler;
    }

    /**
     * Mounts a collection of callbacks onto a base route.
     *
     * @param string $path The route sub pattern/path to mount the callbacks on
     * @param callable|array $handler The callback method
     */
    public static function mount(string $path, $handler)
    {
        list($handler, $groupOptions) = static::mapHandler($handler);

        $initialNamespace = static::$namespace;
        $initialGroupRoute = static::$groupRoute;
        $initialGroupMiddleware = static::$routeGroupMiddleware;

        if ($groupOptions['namespace']) {
            static::$namespace = $groupOptions['namespace'];
        }

        static::$groupRoute = static::$groupRoute . ($path === '/' ? '' : (strpos($path, '/') !== 0 ? "/$path" : $path));

        if ($groupOptions['middleware']) {
            static::$routeGroupMiddleware = $groupOptions['middleware'];
        }

        call_user_func($handler);

        static::$namespace = $initialNamespace;
        static::$groupRoute = $initialGroupRoute;
        static::$routeGroupMiddleware = $initialGroupMiddleware;
    }

    /**
     * Alias for mount
     *
     * @param string $path The route sub pattern/path to mount the callbacks on
     * @param callable|array $handler The callback method
     */
    public static function group(string $path, $handler)
    {
        static::mount($path, $handler);
    }

    // ------------------- main routing stuff -----------------------

    /**
     * Store a route and it's handler
     *
     * @param string $methods Allowed HTTP methods (separated by `|`)
     * @param string $pattern The route pattern/path to match
     * @param string|array|callable $handler The handler for route when matched
     */
    public static function match(string $allowedMethods, string $pattern, $handler)
    {
        $methods = explode('|', $allowedMethods);

        $pattern = static::$groupRoute . '/' . trim($pattern, '/');
        $pattern = static::$groupRoute ? rtrim($pattern, '/') : $pattern;

        list($handler, $routeOptions) = static::mapHandler($handler);

        if (is_string($handler)) {
            $namespace = static::$namespace;

            if ($routeOptions['namespace']) {
                static::$namespace = $routeOptions['namespace'];
            }

            $handler = str_replace('\\\\', '\\', static::$namespace . "\\$handler");

            static::$namespace = $namespace;
        }

        foreach ($methods as $method) {
            static::$routes[$method][] = [
                'pattern' => $pattern,
                'handler' => $handler,
                'name' => $routeOptions['name'] ?? '',
            ];

            if ($routeOptions['middleware'] || !empty(static::$routeGroupMiddleware)) {
                $routeMiddleware = $routeOptions['middleware'] ?? static::$routeGroupMiddleware;

                if (is_array($routeMiddleware)) {
                    foreach ($routeMiddleware as $middleware) {
                        static::$middleware[$method][] = [
                            'pattern' => $pattern,
                            'handler' => $middleware,
                        ];
                    }
                } else {
                    static::$middleware[$method][] = [
                        'pattern' => $pattern,
                        'handler' => $routeMiddleware,
                    ];
                }
            }
        }

        static::$appRoutes[] = [
            'methods' => $methods,
            'pattern' => $pattern,
            'handler' => $handler,
            'name' => $routeOptions['name'] ?? '',
        ];

        if ($routeOptions['name']) {
            static::$namedRoutes[$routeOptions['name']] = $pattern;
        }
    }

    /**
     * Add a route with all available HTTP methods
     *
     * @param string $pattern The route pattern/path to match
     * @param string|array|callable The handler for route when matched
     */
    public static function all(string $pattern, $handler)
    {
        static::match(
            'GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD',
            $pattern,
            $handler
        );
    }

    /**
     * Add a route with GET method
     *
     * @param string $pattern The route pattern/path to match
     * @param string|array|callable The handler for route when matched
     */
    public static function get(string $pattern, $handler)
    {
        static::match('GET', $pattern, $handler);
    }

    /**
     * Add a route with POST method
     *
     * @param string $pattern The route pattern/path to match
     * @param string|array|callable The handler for route when matched
     */
    public static function post(string $pattern, $handler)
    {
        static::match('POST', $pattern, $handler);
    }

    /**
     * Add a route with PUT method
     *
     * @param string $pattern The route pattern/path to match
     * @param string|array|callable The handler for route when matched
     */
    public static function put(string $pattern, $handler)
    {
        static::match('PUT', $pattern, $handler);
    }

    /**
     * Add a route with PATCH method
     *
     * @param string $pattern The route pattern/path to match
     * @param string|array|callable The handler for route when matched
     */
    public static function patch(string $pattern, $handler)
    {
        static::match('PATCH', $pattern, $handler);
    }

    /**
     * Add a route with OPTIONS method
     *
     * @param string $pattern The route pattern/path to match
     * @param string|array|callable The handler for route when matched
     */
    public static function options(string $pattern, $handler)
    {
        static::match('OPTIONS', $pattern, $handler);
    }

    /**
     * Add a route with DELETE method
     *
     * @param string $pattern The route pattern/path to match
     * @param string|array|callable The handler for route when matched
     */
    public static function delete(string $pattern, $handler)
    {
        static::match('DELETE', $pattern, $handler);
    }

    /**
     * Add a route with HEAD method
     *
     * @param string $pattern The route pattern/path to match
     * @param string|array|callable The handler for route when matched
     */
    public static function head(string $pattern, $handler)
    {
        static::match('HEAD', $pattern, $handler);
    }

    /**
     * Add a route that sends an HTTP redirect
     *
     * @param string $from The url to redirect from
     * @param string $to The url to redirect to
     * @param int $status The http status code for redirect
     */
    public static function redirect(
        string $from,
        string $to,
        int $status = 302
    ) {
        static::get($from, function () use ($to, $status) {
            return header("location: $to", true, $status);
        });
    }

    /**
     * Create a resource route for using controllers.
     *
     * This creates a routes that implement CRUD functionality in a controller
     * `/posts` creates:
     * - `/posts` - GET | HEAD - Controller@index
     * - `/posts` - POST - Controller@store
     * - `/posts/{id}` - GET | HEAD - Controller@show
     * - `/posts/create` - GET | HEAD - Controller@create
     * - `/posts/{id}/edit` - GET | HEAD - Controller@edit
     * - `/posts/{id}/edit` - POST | PUT | PATCH - Controller@update
     * - `/posts/{id}/delete` - POST | DELETE - Controller@destroy
     *
     * @param string $pattern The base route to use eg: /post
     * @param array|string $controller to handle route eg: PostController
     */
    public static function resource(string $pattern, $controller)
    {
        if (is_array($controller)) {
            $controllerToCall = $controller[0];

            $controller[0] = function () use ($pattern, $controllerToCall) {
                static::resource('/', $controllerToCall);
            };

            return static::group($pattern, $controller);
        }

        static::match('GET|HEAD', $pattern, "$controller@index");
        static::post($pattern, "$controller@store");
        static::match('GET|HEAD', "$pattern/create", "$controller@create");
        static::match('POST|DELETE', "$pattern/{id}/delete", "$controller@destroy");
        static::match('POST|PUT|PATCH', "$pattern/{id}/edit", "$controller@update");
        static::match('GET|HEAD', "$pattern/{id}/edit", "$controller@edit");
        static::match('GET|HEAD', "$pattern/{id}", "$controller@show");
    }

    /**
     * Create a resource route for using controllers without the create and edit actions.
     *
     * This creates a routes that implement CRUD functionality in a controller
     * `/posts` creates:
     * - `/posts` - GET | HEAD - Controller@index
     * - `/posts` - POST - Controller@store
     * - `/posts/{id}` - GET | HEAD - Controller@show
     * - `/posts/{id}/edit` - POST | PUT | PATCH - Controller@update
     * - `/posts/{id}/delete` - POST | DELETE - Controller@destroy
     *
     * @param string $pattern The base route to use eg: /post
     * @param string $controller to handle route eg: PostController
     */
    public static function apiResource(string $pattern, string $controller)
    {
        static::match('GET|HEAD', $pattern, "$controller@index");
        static::post($pattern, "$controller@store");
        static::match('POST|DELETE', "$pattern/{id}/delete", "$controller@destroy");
        static::match('POST|PUT|PATCH', "$pattern/{id}/edit", "$controller@update");
        static::match('GET|HEAD', "$pattern/{id}", "$controller@show");
    }

    /**
     * Redirect to another route
     *
     * @param string|array $route The route to redirect to
     * @param array|null $data Data to pass to the next route
     */
    public static function push($route, ?array $data = null)
    {
        if (is_array($route)) {
            if (!isset(static::$namedRoutes[$route[0]])) {
                trigger_error('Route named ' . $route[0] . ' not found');
            }

            $route = static::$namedRoutes[$route[0]];
        }

        if ($data) {
            $args = '?';

            foreach ($data as $key => $value) {
                $args .= "$key=$value&";
            }

            $data = rtrim($args, '&');
        }

        return header("location: $route$data");
    }

    /**
     * Get route url by defined route name
     *
     * @param string $routeName
     * @param array|string|null $params
     *
     * @return string
     */
    public static function route(string $routeName, $params = null): string
    {
        if (!isset(static::$namedRoutes[$routeName])) {
            trigger_error('Route named ' . $routeName . ' not found');
        }

        $routePath = static::$namedRoutes[$routeName];
        if ($params) {
            if (is_array($params)) {
                foreach ($params as $key => $value) {
                    if (!preg_match('/{(' . $key . ')}/', $routePath)) {
                        trigger_error('Param "' . $key . '" not found in route "' . static::$namedRoutes[$routeName] . '"');
                    }
                    $routePath = str_replace('{' . $key . '}', $value, $routePath);
                }
            }
            if (is_string($params)) {
                $routePath = preg_replace('/{(.*?)}/', $params, $routePath);
            }
        }

        return $routePath;
    }

    /**
     * Force call the Leaf URL handler
     *
     * @param string $method The method to call
     * @param string $url The uri to force
     */
    public static function handleUrl(string $method, string $url)
    {
        if (isset(static::$routes[$method])) {
            static::handle(
                static::$routes[$method],
                true,
                $url
            );
        }
    }

    /**
     * Get all routes registered in your leaf app
     */
    public static function routes(): array
    {
        return static::$appRoutes;
    }

    /**
     * Set a global namespace for your handlers
     *
     * @param string $namespace The global namespace to set
     */
    public static function setNamespace(string $namespace)
    {
        static::$namespace = $namespace;
    }

    /**
     * Get the global handler namespace.
     *
     * @return string The given namespace if exists
     */
    public static function getNamespace(): string
    {
        return static::$namespace;
    }

    /**
     * Map handler and options
     */
    protected static function mapHandler(
        $handler,
        $options = [
            'name' => null,
            'middleware' => null,
            'namespace' => null,
        ]
    ): array {
        $parsedHandler = $handler;
        $parsedOptions = $options;

        if (is_array($handler)) {
            if (is_string($handler['middleware'] ?? null)) {
                $parsedOptions['middleware'] = (class_exists($handler['middleware'])) ? function () use ($handler) {
                    (new $handler['middleware']())->call();
                } : static::$namedMiddleware[$handler['middleware']] ?? null;
            }

            if (is_array($handler['middleware'] ?? null)) {
                $parsedOptions['middleware'] = [];

                foreach ($handler['middleware'] as $middleware) {
                    if (is_string($middleware)) {
                        $parsedOptions['middleware'][] = static::$namedMiddleware[$middleware] ?? null;
                    } else {
                        $parsedOptions['middleware'][] = $middleware;
                    }
                }
            }

            if (isset($handler['handler'])) {
                $parsedHandler = $handler['handler'];
                unset($handler['handler']);
            } else {
                foreach ($handler as $key => $value) {
                    if (
                        (is_numeric($key) && is_callable($value))
                        || is_numeric($key) && is_string($value) && strpos($value, '@')
                    ) {
                        $parsedHandler = $value;
                        unset($handler[$key]);
                    } else {
                        $parsedOptions[$key] ??= $value;
                    }
                }
            }

            // $parsedOptions = array_merge($handler, $parsedOptions);
        }

        return [$parsedHandler, $parsedOptions];
    }

    /**
     * Add a router hook
     *
     * Available hooks
     * - router.before
     * - router.before.route
     * - router.before.dispatch
     * - router.after.dispatch
     * - router.after.route
     * - router.after
     *
     * @param string $name The hook to set
     * @param callable|null $handler The hook handler
     */
    public static function hook(string $name, callable $handler)
    {
        if (!isset(static::$hooks[$name])) {
            trigger_error("$name is not a valid hook! Refer to the docs for all supported hooks");
        }

        static::$hooks[$name] = $handler;
    }

    /**
     * Call a router hook
     *
     * @param string $name The hook to call
     */
    private static function callHook(string $name)
    {
        return is_callable(static::$hooks[$name]) ? static::$hooks[$name]() : null;
    }

    /**
     * Add middleware
     *
     * This method prepends new middleware to the application middleware stack.
     * The argument must be an instance that subclasses Leaf_Middleware.
     *
     * @param callable|string $middleware The middleware to set
     */
    public static function use($middleware)
    {
        // if (in_array($middleware, static::$middleware)) {
        //     throw new \RuntimeException('Circular Middleware setup detected. Tried to queue the same Middleware twice.');
        // }

        if (is_string($middleware)) {
            $middleware = class_exists($middleware) ? function () use ($middleware) {
                (new $middleware())->call();
            } : static::$namedMiddleware[$middleware];
        }

        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

        for ($i = 0; $i < count($methods); $i++) {
            static::$middleware[$methods[$i]][] = [
                'pattern' => '/.*',
                'handler' => $middleware,
            ];
        }
    }

    /**
     * Register a middleware in your Leaf application by name
     *
     * @param string $name The name of the middleware
     * @param callable $middleware The middleware to register
     */
    public function registerMiddleware(string $name, callable $middleware)
    {
        static::$namedMiddleware[$name] = $middleware;
    }

    /**
     * Return server base Path, and define it if isn't defined.
     *
     * @return string
     */
    public static function getBasePath(): string
    {
        if (static::$serverBasePath === '') {
            static::$serverBasePath = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) . '/';
        }

        return static::$serverBasePath;
    }

    /**
     * Explicilty sets the server base path. To be used when your entry script path differs from your entry URLs.
     * @see https://github.com/bramus/router/issues/82#issuecomment-466956078
     *
     * @param string
     */
    public static function setBasePath($serverBasePath)
    {
        static::$serverBasePath = $serverBasePath;
    }

    /**
     * Define the current relative URI.
     *
     * @return string
     */
    public static function getCurrentUri(): string
    {
        // Get the current Request URI and remove rewrite base path from it (= allows one to run the router in a sub folder)
        $uri = substr(rawurldecode($_SERVER['REQUEST_URI']), strlen(static::getBasePath()));

        if (strstr($uri, '?')) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }

        return '/' . trim($uri, '/');
    }

    /**
     * Get route info of the current route
     *
     * @return array The route info array
     */
    public static function getRoute(): array
    {
        $route = [];
        $currentRoute = static::findRoute();

        if (isset($currentRoute[0])) {
            $route = array_merge($route, [
                'pattern' => $currentRoute[0]['route']['pattern'],
                'path' => static::getCurrentUri(),
                'method' => \Leaf\Http\Request::getMethod(),
                'name' => $currentRoute[0]['route']['name'] ?? null,
                'handler' => $currentRoute[0]['route']['handler'],
                'params' => $currentRoute[0]['params'] ?? [],
            ]);
        }

        return array_merge($route);
    }

    /**
     * Find the current route
     *
     * @return array
     */
    public static function findRoute(
        ?array $routes = null,
        ?string $uri = null,
        $returnFirst = true
    ): array {
        $handledRoutes = [];
        $uri = $uri ?? static::getCurrentUri();
        $routes = $routes ?? static::$routes[\Leaf\Http\Request::getMethod()];

        foreach ($routes as $route) {
            // Replace all curly braces matches {} into word patterns (like Laravel)
            $route['pattern'] = preg_replace('/\/{(.*?)}/', '/(.*?)', $route['pattern']);

            // we have a match!
            if (preg_match_all('#^' . $route['pattern'] . '$#', $uri, $matches, PREG_OFFSET_CAPTURE)) {
                // Rework matches to only contain the matches, not the orig string
                $matches = array_slice($matches, 1);

                // Extract the matched URL parameters (and only the parameters)
                $params = array_map(function ($match, $index) use ($matches) {
                    // We have a following parameter: take the substring from the current param position until the next one's position (thank you PREG_OFFSET_CAPTURE)
                    if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && $matches[$index + 1][0][1] != -1 && is_array($matches[$index + 1][0])) {
                        return trim(substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
                    }

                    // We have no following parameters: return the whole lot
                    return isset($match[0][0]) ? trim($match[0][0], '/') : null;
                }, $matches, array_keys($matches));

                $paramsWithSlash = array_filter($params, function ($param) {
                    return strpos($param, '/') !== false;
                });

                // if any of the params contain /, we should skip this route
                if (!empty($paramsWithSlash)) {
                    continue;
                }

                $routeData = [
                    'params' => $params,
                    'handler' => $route['handler'],
                    'route' => $route,
                ];

                $handledRoutes[] = $routeData;

                if ($returnFirst) {
                    break;
                }
            }
        }

        return $handledRoutes;
    }

    /**
     * Dispatch your application routes
     */
    public static function run(?callable $callback = null)
    {
        $requestedMethod = \Leaf\Http\Request::getMethod();
        $appDown = _env('APP_DOWN', \Leaf\Anchor::toBool(\Leaf\Config::getStatic('app.down')) ?? false);

        if ($appDown === true) {
            if (!static::$downHandler) {
                static::$downHandler = function () {
                    \Leaf\Exception\General::defaultDown();
                };
            }

            return static::invoke(static::$downHandler);
        }

        if (is_callable($callback)) {
            static::hook('router.after', $callback);
        }

        static::callHook('router.before');

        if (isset(static::$middleware[$requestedMethod])) {
            static::handle(static::$middleware[$requestedMethod]);
        }

        static::callHook('router.before.route');

        $numHandled = 0;

        if (isset(static::$routes[$requestedMethod])) {
            $numHandled = static::handle(
                null,
                true
            );
        }

        if ($numHandled === 0) {
            if (!static::$notFoundHandler) {
                static::$notFoundHandler = function () {
                    \Leaf\Exception\General::default404();
                };
            }

            static::invoke(static::$notFoundHandler);
        }

        // if it originally was a HEAD request, clean up after ourselves by emptying the output buffer
        if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
            ob_end_clean();
        }

        static::callHook('router.after.route');

        restore_error_handler();

        return static::callHook('router.after') ?? ($numHandled !== 0);
    }

    /**
     * Handle a set of routes: if a match is found, execute the relating handling function.
     *
     * @param array $routes Collection of route patterns and their handling functions
     * @param bool $quitAfterRun Does the handle function need to quit after one route was matched?
     * @param string|null $uri The URI to call (automatically set if nothing is passed).
     *
     * @return int The number of routes handled
     */
    private static function handle(?array $routes = null, bool $quitAfterRun = false, ?string $uri = null): int
    {
        $uri = $uri ?? static::getCurrentUri();
        $routeToHandle = static::findRoute($routes, $uri, $quitAfterRun);

        // hacky solution to handle middleware catching all middleware with pattern (.*?)
        $routesToRun = array_filter($routeToHandle, function ($route) use ($uri) {
            return $route['route']['pattern'] === $uri || $route['route']['pattern'] === '/.*' || implode('/', $route['params'] ?? []) === ltrim($uri, '/');
        });

        if (empty($routesToRun)) {
            $routesToRun = $routeToHandle;
        }

        foreach ($routesToRun as $currentRoute) {
            static::invoke($currentRoute['handler'], $currentRoute['params']);
        }

        return count($routesToRun);
    }

    private static function invoke($handler, $params = [])
    {
        if (is_callable($handler)) {
            call_user_func_array(
                $handler,
                $params
            );
        } elseif (stripos($handler, '@') !== false) {
            list($controller, $method) = explode('@', $handler);

            if (!class_exists($controller)) {
                trigger_error("$controller not found. Cross-check the namespace if you're sure the file exists");
            }

            if (!method_exists($controller, $method)) {
                trigger_error("$method method not found in $controller");
            }

            // First check if is a static method, directly trying to invoke it.
            // If isn't a valid static method, we will try as a normal method invocation.
            if (call_user_func_array([new $controller(), $method], $params) === false) {
                // Try to call the method as a non-static method. (the if does nothing, only avoids the notice)
                if (forward_static_call_array([$controller, $method], $params) === false)
                ;
            }
        }
    }
}
