<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks\Field;

use FlatFileCms\Blocks\FieldValueException;

final class FieldSettings
{
    /** @param array<string, mixed> $settings */
    public static function integer(array $settings, string $name, ?string $alias = null): ?int
    {
        $value = $settings[$name] ?? ($alias === null ? null : ($settings[$alias] ?? null));
        if ($value === null) {
            return null;
        }
        if (!is_int($value) || $value < 0) {
            throw new FieldValueException('INVALID_SCHEMA_RULE', sprintf('Rule "%s" must be a non-negative integer.', $name));
        }

        return $value;
    }

    /** @param array<string, mixed> $settings */
    public static function number(array $settings, string $name): int|float|null
    {
        $value = $settings[$name] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_int($value) && !is_float($value)) {
            throw new FieldValueException('INVALID_SCHEMA_RULE', sprintf('Rule "%s" must be numeric.', $name));
        }

        return $value;
    }

    /** @param array<string, mixed> $settings
     *
     * @return list<string> */
    public static function allowedValues(array $settings): array
    {
        $raw = $settings['allowedValues'] ?? $settings['options'] ?? null;
        if (!is_array($raw) || !array_is_list($raw) || $raw === []) {
            throw new FieldValueException('INVALID_SCHEMA_RULE', 'Choice field requires a non-empty options list.');
        }

        $values = [];
        foreach ($raw as $option) {
            if (is_string($option) && $option !== '') {
                $values[] = $option;

                continue;
            }
            if (is_array($option) && isset($option['value']) && is_string($option['value']) && $option['value'] !== '') {
                $values[] = $option['value'];

                continue;
            }

            throw new FieldValueException('INVALID_SCHEMA_RULE', 'Choice options must contain non-empty string values.');
        }

        return array_values(array_unique($values));
    }
}
