<?php

declare(strict_types=1);

namespace FlatFileCms\Domain\Content;

use InvalidArgumentException;

final readonly class PageIdentity
{
    /** @param non-empty-list<Slug> $segments */
    private function __construct(private array $segments) {}

    public static function fromString(string $value): self
    {
        if ($value === '' || str_starts_with($value, '/') || str_ends_with($value, '/')) {
            throw new InvalidArgumentException('Page identity must be a non-empty relative path.');
        }

        $rawSegments = explode('/', $value);
        $segments = [];
        foreach ($rawSegments as $rawSegment) {
            $segments[] = Slug::fromString($rawSegment);
        }

        if (count($segments) > 1) {
            foreach ($segments as $segment) {
                if ($segment->value() === 'homepage') {
                    throw new InvalidArgumentException('The homepage identity cannot be nested.');
                }
            }
        }

        /** @var non-empty-list<Slug> $segments */
        return new self($segments);
    }

    public static function homepage(): self
    {
        return new self([Slug::fromString('homepage')]);
    }

    public function value(): string
    {
        return implode('/', array_map(
            static fn(Slug $segment): string => $segment->value(),
            $this->segments,
        ));
    }

    public function isHomepage(): bool
    {
        return count($this->segments) === 1 && $this->segments[0]->value() === 'homepage';
    }

    /** @return non-empty-list<Slug> */
    public function segments(): array
    {
        return $this->segments;
    }
}
