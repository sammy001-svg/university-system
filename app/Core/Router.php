<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

/**
 * Route table with {param} placeholders, per-route middleware and groups.
 */
final class Router
{
    private array $routes = [];
    private array $names = [];
    private array $groupStack = [];

    /** The router handling this request; one is built per request in App. */
    private static ?self $current = null;

    public function __construct()
    {
        self::$current = $this;
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    /**
     * Whether the signed-in user could open this path: it has to match a route,
     * and they have to satisfy every permission or role its middleware demands.
     *
     * Only middleware is read, so a controller may still refuse with its own
     * authorize() check. This answers the narrower question of whether it is
     * safe to send somebody to a page, rather than bouncing them into a 403.
     */
    public function allows(string $uri, string $method = 'GET'): bool
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        foreach ($this->routes[$method] ?? [] as $route) {
            if (!preg_match($route['pattern'], $path)) {
                continue;
            }
            foreach ($route['middleware'] as $middleware) {
                [$name, $argument] = array_pad(explode(':', $middleware, 2), 2, null);
                if ($argument === null || $argument === '') {
                    continue;
                }
                $values = array_map('trim', explode(',', $argument));
                if ($name === 'permission' && !Auth::can($values)) {
                    return false;
                }
                if ($name === 'role' && !Auth::hasRole($values)) {
                    return false;
                }
            }
            return true;
        }
        return false;   // nothing answers that path: better to send them home
    }

    public function get(string $uri, mixed $action, array $middleware = []): self
    {
        return $this->add('GET', $uri, $action, $middleware);
    }

    public function post(string $uri, mixed $action, array $middleware = []): self
    {
        return $this->add('POST', $uri, $action, $middleware);
    }

    public function put(string $uri, mixed $action, array $middleware = []): self
    {
        return $this->add('PUT', $uri, $action, $middleware);
    }

    public function patch(string $uri, mixed $action, array $middleware = []): self
    {
        return $this->add('PATCH', $uri, $action, $middleware);
    }

    public function delete(string $uri, mixed $action, array $middleware = []): self
    {
        return $this->add('DELETE', $uri, $action, $middleware);
    }

    public function any(array $methods, string $uri, mixed $action, array $middleware = []): self
    {
        foreach ($methods as $method) {
            $this->add(strtoupper($method), $uri, $action, $middleware);
        }
        return $this;
    }

    /** Group routes under a shared prefix and middleware stack. */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    private function add(string $method, string $uri, mixed $action, array $middleware): self
    {
        $prefix           = '';
        $groupMiddleware  = [];
        foreach ($this->groupStack as $group) {
            $prefix          .= isset($group['prefix']) ? '/' . trim($group['prefix'], '/') : '';
            $groupMiddleware  = array_merge($groupMiddleware, $group['middleware'] ?? []);
        }

        $uri  = $prefix . '/' . trim($uri, '/');
        $uri  = '/' . trim($uri, '/');
        $uri  = $uri === '/' ? '/' : rtrim($uri, '/');

        $this->routes[$method][] = [
            'uri'        => $uri,
            'pattern'    => $this->compile($uri),
            'action'     => $action,
            'middleware' => array_values(array_unique(array_merge($groupMiddleware, $middleware))),
            'name'       => null,
        ];

        return $this;
    }

    /** Give the most recently registered route a name. */
    public function name(string $name): self
    {
        $methods = array_keys($this->routes);
        $last    = end($methods);
        if ($last === false) {
            return $this;
        }
        $index = array_key_last($this->routes[$last]);
        $this->routes[$last][$index]['name'] = $name;
        $this->names[$name] = $this->routes[$last][$index]['uri'];
        return $this;
    }

    private function compile(string $uri): string
    {
        // {id} matches a segment; {id?} makes the whole segment optional.
        $pattern = preg_replace('#/\{([a-zA-Z_][a-zA-Z0-9_]*)\?\}#', '(?:/(?P<$1>[^/]+))?', $uri);
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', (string) $pattern);
        return '#^' . $pattern . '$#';
    }

    public function routeUrl(string $name, array $params = []): string
    {
        $uri = $this->names[$name] ?? '/';
        foreach ($params as $key => $value) {
            $uri = str_replace(['{' . $key . '}', '{' . $key . '?}'], (string) $value, $uri);
        }
        return preg_replace('#/\{[^}]+\?\}#', '', $uri) ?? $uri;
    }

    /** Match the request and run the matching action through its middleware. */
    public function dispatch(Request $request): mixed
    {
        $method = $request->method();
        $uri    = $request->uri();

        foreach ($this->routes[$method] ?? [] as $route) {
            if (!preg_match($route['pattern'], $uri, $matches)) {
                continue;
            }
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            $request->setAttribute('route', $route);
            $request->setAttribute('params', $params);

            foreach ($route['middleware'] as $middleware) {
                $this->runMiddleware($middleware, $request);
            }

            return $this->runAction($route['action'], $request, $params);
        }

        // The path exists but not for this verb -> 405, otherwise 404.
        foreach ($this->routes as $verb => $routes) {
            if ($verb === $method) {
                continue;
            }
            foreach ($routes as $route) {
                if (preg_match($route['pattern'], $uri)) {
                    throw new HttpException(405, 'Method Not Allowed');
                }
            }
        }
        throw new HttpException(404, 'The page you requested could not be found.');
    }

    private function runMiddleware(string $middleware, Request $request): void
    {
        [$name, $argument] = array_pad(explode(':', $middleware, 2), 2, null);
        $class = sprintf('App\Middleware\%sMiddleware', ucfirst($name));
        if (!class_exists($class)) {
            throw new HttpException(500, "Middleware not found: {$class}");
        }
        (new $class())->handle($request, $argument);
    }

    private function runAction(mixed $action, Request $request, array $params): mixed
    {
        if (is_callable($action)) {
            return $action($request, ...array_values($params));
        }
        if (is_string($action) && str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);
            $class = sprintf('App\Controllers\%s', $controller);
            if (!class_exists($class)) {
                throw new HttpException(500, "Controller not found: {$class}");
            }
            $instance = new $class();
            if (!method_exists($instance, $method)) {
                throw new HttpException(500, "Method {$method}() not found on {$class}");
            }
            return $instance->{$method}($request, ...array_values($params));
        }
        throw new HttpException(500, 'Invalid route action.');
    }

    public function routes(): array
    {
        return $this->routes;
    }
}
