<?php

declare(strict_types=1);

namespace ReaCms\Core\Routing;

use Closure;
use InvalidArgumentException;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\Response;

final class Route
{
    /** @var Closure(Request, array<string, string>): Response */
    private readonly Closure $handler;

    /** @var list<string> */
    private readonly array $parameterNames;

    private readonly string $pattern;

    /**
     * @param callable(Request, array<string, string>): Response $handler
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        callable $handler,
    ) {
        if (!str_starts_with($path, '/')) {
            throw new InvalidArgumentException('Route paths must begin with a slash.');
        }

        $this->handler = Closure::fromCallable($handler);
        [$this->pattern, $this->parameterNames] = $this->compile($path);
    }

    public function method(): string
    {
        return strtoupper($this->method);
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, string>|null
     */
    public function match(string $path): ?array
    {
        $matches = [];

        if (preg_match($this->pattern, $path, $matches) !== 1) {
            return null;
        }

        $parameters = [];
        foreach ($this->parameterNames as $name) {
            $parameters[$name] = $matches[$name];
        }

        return $parameters;
    }

    /**
     * @param array<string, string> $parameters
     */
    public function run(Request $request, array $parameters): Response
    {
        return ($this->handler)($request, $parameters);
    }

    /**
     * @return array{string, list<string>}
     */
    private function compile(string $path): array
    {
        $names = [];
        $quoted = preg_quote($path, '#');
        $pattern = preg_replace_callback(
            '/\\\\\{([A-Za-z][A-Za-z0-9_]*)\\\\\}/',
            static function (array $matches) use (&$names): string {
                $names[] = $matches[1];

                return sprintf('(?P<%s>[^/]+)', $matches[1]);
            },
            $quoted,
        );

        if ($pattern === null) {
            throw new InvalidArgumentException('The route path could not be compiled.');
        }

        return ['#^' . $pattern . '$#D', $names];
    }
}
