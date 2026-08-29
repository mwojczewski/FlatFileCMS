<?php

declare(strict_types=1);

namespace FlatFileCms\Domain\Content;

use InvalidArgumentException;

final readonly class Slug
{
    private const int MAX_LENGTH = 120;

    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        if ($value === '' || strlen($value) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Slug must contain between 1 and 120 characters.');
        }

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value) !== 1) {
            throw new InvalidArgumentException('Slug may contain lowercase ASCII letters, digits and single hyphens.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }
}
