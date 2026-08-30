<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks\Field;

use FlatFileCms\Blocks\FieldDefinition;
use FlatFileCms\Blocks\FieldValueException;

final readonly class BooleanFieldType implements FieldType
{
    public function name(): string
    {
        return 'boolean';
    }

    public function validateDefinition(FieldDefinition $definition): void {}

    public function normalize(mixed $value, FieldDefinition $definition, FieldContext $context): bool
    {
        if (\is_bool($value)) {
            return $value;
        }
        if (\is_string($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        throw new FieldValueException('INVALID_TYPE', 'Value must be boolean.');
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
