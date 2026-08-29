<?php

declare(strict_types=1);

namespace FlatFileCms\Infrastructure\Filesystem;

use InvalidArgumentException;

final readonly class FileRevision
{
    private const string MISSING = 'missing';

    private function __construct(private string $value) {}

    public static function missing(): self
    {
        return new self(self::MISSING);
    }

    public static function fromContents(string $contents): self
    {
        return new self(hash('sha256', $contents));
    }

    public static function fromString(string $value): self
    {
        if ($value !== self::MISSING && preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
            throw new InvalidArgumentException('File revision must be a SHA-256 hash or "missing".');
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
