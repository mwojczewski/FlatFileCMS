<?php

declare(strict_types=1);

namespace FlatFileCms\Support;

use InvalidArgumentException;

final class ContentData
{
    /** @return array<string, mixed> */
    public static function map(mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('Field "%s" must be a mapping.', $field));
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new InvalidArgumentException(sprintf('Field "%s" must use string keys.', $field));
            }

            $result[$key] = $item;
        }

        return $result;
    }

    /** @return list<mixed> */
    public static function list(mixed $value, string $field): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException(sprintf('Field "%s" must be a list.', $field));
        }

        return $value;
    }

    public static function string(mixed $value, string $field): string
    {
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException(sprintf('Field "%s" must be a non-empty string.', $field));
        }

        return $value;
    }

    public static function boolean(mixed $value, string $field): bool
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException(sprintf('Field "%s" must be boolean.', $field));
        }

        return $value;
    }

    public static function integer(mixed $value, string $field): int
    {
        if (!is_int($value)) {
            throw new InvalidArgumentException(sprintf('Field "%s" must be an integer.', $field));
        }

        return $value;
    }
}
