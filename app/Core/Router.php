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
