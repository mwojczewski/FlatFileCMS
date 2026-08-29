<?php

declare(strict_types=1);

namespace FlatFileCms\Infrastructure\Filesystem;

use InvalidArgumentException;

final readonly class RelativePath
{
    private const int MAX_LENGTH = 1024;
    private const int MAX_SEGMENT_LENGTH = 255;

    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        if (strlen($value) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Relative path is too long.');
        }

        if (str_contains($value, "\0") || preg_match('/[\x01-\x1F\x7F]/', $value) === 1) {
            throw new InvalidArgumentException('Relative path contains control characters.');
        }

        if (str_contains($value, '\\') || str_starts_with($value, '/') || preg_match('/^[A-Za-z]:/', $value) === 1) {
            throw new InvalidArgumentException('Absolute and platform-specific paths are not allowed.');
        }

        if (preg_match('/[<>:"|?*]/', $value) === 1) {
            throw new InvalidArgumentException('Relative path contains non-portable characters.');
        }

        if ($value === '') {
            return new self('');
        }

        if (str_ends_with($value, '/') || str_contains($value, '//')) {
            throw new InvalidArgumentException('Relative path contains an empty segment.');
        }

        foreach (explode('/', $value) as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Relative path traversal is not allowed.');
            }

            if (rtrim($segment, ". ") !== $segment) {
                throw new InvalidArgumentException('Relative path segment cannot end with a dot or space.');
            }

            if (strlen($segment) > self::MAX_SEGMENT_LENGTH) {
                throw new InvalidArgumentException('Relative path segment is too long.');
            }

            $portableName = strtoupper(explode('.', $segment, 2)[0]);
            if (in_array($portableName, self::reservedWindowsNames(), true)) {
                throw new InvalidArgumentException('Relative path contains a reserved filename.');
            }
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isRoot(): bool
    {
        return $this->value === '';
    }

    /** @return list<string> */
    public function segments(): array
    {
        return $this->value === '' ? [] : explode('/', $this->value);
    }

    /** @return list<string> */
    private static function reservedWindowsNames(): array
    {
        return [
            'CON', 'PRN', 'AUX', 'NUL',
            'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9',
            'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9',
        ];
    }
}
