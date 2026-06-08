<?php
declare(strict_types=1);

namespace App;

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\Middleware;
use App\Middleware\RateLimitMiddleware;

/**
 * Minimal routing table: method + path -> [Controller, action] with per-route middleware.
 * Supports {param} placeholders. Returns clean 404/405 responses.
 */
final class Router
{
    /** @var list<array{method:string,regex:string,handler:array,middleware:list<string>}> */
    private array $routes = [];

    /**
     * @param array|callable $handler [ControllerClass, 'action'] or a callable
     * @param list<string>   $middleware aliases, e.g. ['guest','csrf','throttle:login,5,300']
     */
    public function add(string $method, string $path, array|callable $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method'     => strtoupper($method),
            'regex'      => $this->compile($path),
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    /** Load an array of [method, path, handler, middleware] route definitions. */
    public function load(array $routes): void
    {
        foreach ($routes as $route) {
            $this->add($route[0], $route[1], $route[2], $route[3] ?? []);
        }
    }

    public function dispatch(Request $request): void
    {
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }
            if ($route['method'] !== $request->method) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            // Named route parameters.
            foreach ($matches as $key => $value) {
                if (!is_int($key)) {
                    $request->params[$key] = $value;
                }
            }

            // Global middleware (always runs first): locale negotiation.
            (new \App\Middleware\LocaleMiddleware())->handle($request);

            // Per-route middleware, in declared order.
            foreach ($route['middleware'] as $alias) {
                $this->makeMiddleware($alias)->handle($request);
            }

            $this->call($route['handler'], $request);
            return;
        }

        if ($allowedMethods !== []) {
            header('Allow: ' . implode(', ', array_unique($allowedMethods)));
            abort(405);
        }
        abort(404);
    }

    private function call(array|callable $handler, Request $request): void
    {
        if (is_array($handler)) {
            [$class, $action] = $handler;
            $controller = new $class();
            $controller->{$action}($request);
            return;
        }
        $handler($request);
    }

    private function compile(string $path): string
    {
        $pattern = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static fn (array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            $path
        );
        return '#^' . $pattern . '$#';
    }

    /** Resolve a middleware alias (with optional :args) to an instance. */
    private function makeMiddleware(string $alias): Middleware
    {
        $args = [];
        if (str_contains($alias, ':')) {
            [$alias, $argString] = explode(':', $alias, 2);
            $args = explode(',', $argString);
        }

        return match ($alias) {
            'auth'     => new AuthMiddleware($args[0] ?? null),
            'guest'    => new GuestMiddleware(),
            'csrf'     => new CsrfMiddleware(),
            'throttle' => new RateLimitMiddleware(
                $args[0] ?? 'global',
                (int) ($args[1] ?? 60),
                (int) ($args[2] ?? 60)
            ),
            default    => throw new \InvalidArgumentException("Unknown middleware: {$alias}"),
        };
    }
}
