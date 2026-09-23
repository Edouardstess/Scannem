<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;

/**
 * Regex-compiled routing table with per-route middleware.
 *
 * Routes are declared in routes/web.php as
 * [method, pattern, [Controller::class, 'action'], middleware...].
 */
final class Router
{
    /** @var array<string, array<int, array{regex: string, params: array<int,string>, handler: mixed, middleware: array<int,string>, name: ?string}>> */
    private array $routes = [];

    /** @var array<string, string> route name => pattern */
    private array $names = [];

    /** @var array<int, string> Middleware applied to every route. */
    private array $globalMiddleware = [];

    private ?string $matchedName = null;

    public function middleware(string ...$middleware): self
    {
        foreach ($middleware as $item) {
            $this->globalMiddleware[] = $item;
        }

        return $this;
    }

    public function get(string $pattern, mixed $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->add('GET', $pattern, $handler, $middleware, $name);
    }

    public function post(string $pattern, mixed $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->add('POST', $pattern, $handler, $middleware, $name);
    }

    public function put(string $pattern, mixed $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->add('PUT', $pattern, $handler, $middleware, $name);
    }

    public function patch(string $pattern, mixed $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->add('PATCH', $pattern, $handler, $middleware, $name);
    }

    public function delete(string $pattern, mixed $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->add('DELETE', $pattern, $handler, $middleware, $name);
    }

    public function add(string $method, string $pattern, mixed $handler, array $middleware = [], ?string $name = null): self
    {
        $normalised = '/' . trim($pattern, '/');
        $params = [];

        // {id} matches a path segment; {id:\d+} constrains it.
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#',
            static function (array $matches) use (&$params): string {
                $params[] = $matches[1];
                $constraint = $matches[2] ?? '[^/]+';

                return '(' . $constraint . ')';
            },
            $normalised
        );

        $this->routes[strtoupper($method)][] = [
            'regex'      => '#^' . $regex . '$#u',
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => $middleware,
            'name'       => $name,
            'pattern'    => $normalised,
        ];

        if ($name !== null) {
            $this->names[$name] = $normalised;
        }

        return $this;
    }

    /**
     * Resolve and run the route matching the request.
     *
     * @throws HttpException 404 when no pattern matches, 405 when the path
     *         exists under another verb (which is a genuinely different
     *         answer for a client and for debugging).
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            array_shift($matches);
            $parameters = array_combine($route['params'], $matches) ?: [];
            $this->matchedName = $route['name'];

            return $this->run($route, $request, $parameters);
        }

        foreach ($this->routes as $otherMethod => $routes) {
            if ($otherMethod === $method) {
                continue;
            }

            foreach ($routes as $route) {
                if (preg_match($route['regex'], $path) === 1) {
                    throw new HttpException(405, 'Method not allowed.');
                }
            }
        }

        throw new HttpException(404, 'Page not found.');
    }

    /** @param array<string, string> $parameters */
    private function run(array $route, Request $request, array $parameters): Response
    {
        $pipeline = array_merge($this->globalMiddleware, $route['middleware']);

        foreach ($pipeline as $middlewareClass) {
            /** @var \App\Middleware\MiddlewareInterface $middleware */
            $middleware = new $middlewareClass();
            $result = $middleware->handle($request, $parameters);

            if ($result instanceof Response) {
                return $result;
            }
        }

        $handler = $route['handler'];

        if (is_callable($handler) && !is_array($handler)) {
            return $handler($request, $parameters);
        }

        [$class, $action] = $handler;
        $controller = new $class();

        return $controller->{$action}($request, $parameters);
    }

    public function matchedName(): ?string
    {
        return $this->matchedName;
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function routes(): array
    {
        return $this->routes;
    }

    /** Build a path from a named route: route('admin.gallery.show', ['id' => 4]). */
    public function route(string $name, array $parameters = []): string
    {
        $pattern = $this->names[$name] ?? null;

        if ($pattern === null) {
            return '/';
        }

        return preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#',
            static fn (array $m): string => rawurlencode((string) ($parameters[$m[1]] ?? '')),
            $pattern
        ) ?? '/';
    }
}
