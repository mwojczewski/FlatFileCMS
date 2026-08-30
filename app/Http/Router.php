<?php

declare(strict_types=1);

namespace FlatFileCms\Http;

use Closure;
use InvalidArgumentException;

final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @param Closure(Request): Response $handler */
    public function get(string $pattern, Closure $handler, string $name): void
    {
        $this->add(['GET', 'HEAD'], $pattern, $handler, $name);
    }

    /** @param Closure(Request): Response $handler */
    public function post(string $pattern, Closure $handler, string $name): void
    {
        $this->add(['POST'], $pattern, $handler, $name);
    }

    /**
     * @param list<string> $methods
     * @param Closure(Request): Response $handler
     */
    public function add(array $methods, string $pattern, Closure $handler, string $name): void
    {
        if ($this->hasName($name)) {
            throw new InvalidArgumentException(sprintf('Route name "%s" is already registered.', $name));
        }

        $normalizedPattern = $pattern === '/' ? '/' : rtrim($pattern, '/');
        $this->routes[] = new Route($methods, $normalizedPattern, $handler, $name);
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;

        foreach ($this->routes as $route) {
            $parameters = $route->parameters($request->path());
            if ($parameters === null) {
                continue;
            }

            $pathMatched = true;
            if (!$route->allows($request->method())) {
                continue;
            }

            $response = $route->run($request->withAttributes($parameters));
            if ($request->method() === 'HEAD') {
                return new Response('', $response->status(), $response->headers());
            }

            return $response;
        }

        if ($pathMatched) {
            throw new HttpException(405, 'METHOD_NOT_ALLOWED', 'Method not allowed');
        }

        throw new HttpException(404, 'ROUTE_NOT_FOUND', 'Route not found');
    }

    private function hasName(string $name): bool
    {
        foreach ($this->routes as $route) {
            if ($route->name() === $name) {
                return true;
            }
        }

        return false;
    }
}
