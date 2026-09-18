<?php

declare(strict_types=1);

namespace FlatFileCms\Http;

use Closure;
use InvalidArgumentException;

final readonly class Route
{
    private string $expression;

    /** @var list<string> */
    private array $parameterNames;

    /**
     * @param list<string> $methods
     * @param Closure(Request): Response $handler
     */
    public function __construct(
        private array $methods,
        private string $pattern,
        private Closure $handler,
        private string $name,
    ) {
        if ($methods === [] || $pattern === '' || $pattern[0] !== '/') {
            throw new InvalidArgumentException('A route requires methods and an absolute URL pattern.');
        }

        $names = [];
        $quoted = preg_quote($this->pattern, '#');
        $expression = preg_replace_callback(
            '/\\\\\{([A-Za-z_][A-Za-z0-9_]*)(\\\\\*)?\\\\\}/',
            static function (array $match) use (&$names): string {
                $names[] = $match[1];

                return ($match[2] ?? '') === '\\*' ? '(.+)' : '([^/]+)';
            },
            $quoted,
        );
        if (!\is_string($expression)) {
            throw new InvalidArgumentException('Route pattern cannot be compiled.');
        }

        $this->expression = "#^{$expression}$#D";
        $this->parameterNames = $names;
    }

    public function allows(string $method): bool
    {
        return \in_array(strtoupper($method), $this->methods, true);
    }

    public function matchesPath(string $path): bool
    {
        return $this->parameters($path) !== null;
    }

    /** @return array<string, string>|null */
    public function parameters(string $path): ?array
    {
        if (preg_match($this->expression, $path, $matches) !== 1) {
            return null;
        }

        array_shift($matches);
        $parameters = [];
        foreach ($this->parameterNames as $index => $name) {
            $parameters[$name] = rawurldecode($matches[$index] ?? throw new InvalidArgumentException('Invalid index range.'));
        }

        return $parameters;
    }

    public function run(Request $request): Response
    {
        return ($this->handler)($request);
    }

    public function name(): string
    {
        return $this->name;
    }
}
