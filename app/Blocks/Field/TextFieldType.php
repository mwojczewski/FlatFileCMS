<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks\Field;

use FlatFileCms\Blocks\FieldDefinition;
use FlatFileCms\Blocks\FieldValueException;

final readonly class TextFieldType implements FieldType
{
    public function __construct(private string $name) {}

    public function name(): string
    {
        return $this->name;
    }

    public function validateDefinition(FieldDefinition $definition): void
    {
        $minimum = FieldSettings::integer($definition->settings(), 'minLength', 'min');
        $maximum = FieldSettings::integer($definition->settings(), 'maxLength', 'max');
        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            throw new FieldValueException('INVALID_SCHEMA_RULE', 'Minimum length cannot exceed maximum length.');
        }
    }

    public function normalize(mixed $value, FieldDefinition $definition, FieldContext $context): string
    {
        if (!\is_string($value)) {
            throw new FieldValueException('INVALID_TYPE', 'Value must be a string.');
        }

        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
        $length = mb_strlen($value);
        $minimum = FieldSettings::integer($definition->settings(), 'minLength', 'min');
        $maximum = FieldSettings::integer($definition->settings(), 'maxLength', 'max');
        if ($minimum !== null && $length < $minimum) {
            throw new FieldValueException('TOO_SHORT', \sprintf('Value must contain at least %d characters.', $minimum));
        }
        if ($maximum !== null && $length > $maximum) {
            throw new FieldValueException('TOO_LONG', \sprintf('Value may contain at most %d characters.', $maximum));
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
