<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks\Field;

use FlatFileCms\Blocks\FieldDefinition;
use FlatFileCms\Blocks\FieldValueException;

final readonly class NumberFieldType implements FieldType
{
    public function name(): string
    {
        return 'number';
    }

    public function validateDefinition(FieldDefinition $definition): void
    {
        $integerOnly = $definition->settings()['integer'] ?? false;
        if (!is_bool($integerOnly)) {
            throw new FieldValueException('INVALID_SCHEMA_RULE', 'Rule "integer" must be boolean.');
        }
        $minimum = FieldSettings::number($definition->settings(), 'min');
        $maximum = FieldSettings::number($definition->settings(), 'max');
        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            throw new FieldValueException('INVALID_SCHEMA_RULE', 'Minimum cannot exceed maximum.');
        }
    }

    public function normalize(mixed $value, FieldDefinition $definition, FieldContext $context): int|float
    {
        if (is_string($value) && is_numeric($value)) {
            $value = str_contains($value, '.') ? (float) $value : (int) $value;
        }
        if ((!is_int($value) && !is_float($value)) || (is_float($value) && !is_finite($value))) {
            throw new FieldValueException('INVALID_TYPE', 'Value must be a finite number.');
        }

        $integerOnly = $definition->settings()['integer'] ?? false;
        if (!is_bool($integerOnly)) {
            throw new FieldValueException('INVALID_SCHEMA_RULE', 'Rule "integer" must be boolean.');
        }
        if ($integerOnly && !is_int($value)) {
            throw new FieldValueException('NOT_INTEGER', 'Value must be an integer.');
        }

        $minimum = FieldSettings::number($definition->settings(), 'min');
        $maximum = FieldSettings::number($definition->settings(), 'max');
        if ($minimum !== null && $value < $minimum) {
            throw new FieldValueException('TOO_SMALL', sprintf('Value must be at least %s.', (string) $minimum));
        }
        if ($maximum !== null && $value > $maximum) {
            throw new FieldValueException('TOO_LARGE', sprintf('Value may be at most %s.', (string) $maximum));
        }

        return $value;
    }

    public function localize(
        mixed $value,
        string $locale,
        FieldDefinition $definition,
        FieldContext $context,
    ): mixed {
        return $value;
    }
}
