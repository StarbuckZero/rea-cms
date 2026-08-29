<?php

declare(strict_types=1);

namespace ReaCms\Core\Routing;

use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\Response;

final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /**
     * @param callable(Request, array<string, string>): Response $handler
     */
    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = new Route($method, $path, $handler);
    }

    /**
     * @param callable(Request, array<string, string>): Response $handler
     */
    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /**
     * @param callable(Request, array<string, string>): Response $handler
     */
    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(Request $request): Response
    {
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            $parameters = $route->match($request->path());

            if ($parameters === null) {
                continue;
            }

            if ($route->method() === $request->method()) {
                return $route->run($request, $parameters);
            }

            $allowedMethods[] = $route->method();
        }

        if ($allowedMethods !== []) {
            throw new MethodNotAllowed(array_values(array_unique($allowedMethods)));
        }

        throw new RouteNotFound();
    }
}
