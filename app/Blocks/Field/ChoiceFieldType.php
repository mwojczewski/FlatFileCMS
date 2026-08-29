<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks\Field;

use FlatFileCms\Blocks\FieldDefinition;
use FlatFileCms\Blocks\FieldValueException;

final readonly class ChoiceFieldType implements FieldType
{
    public function __construct(
        private string $name,
        private bool $multiple,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function validateDefinition(FieldDefinition $definition): void
    {
        FieldSettings::allowedValues($definition->settings());
        if (!$this->multiple) {
            return;
        }

        $minimum = FieldSettings::integer($definition->settings(), 'minItems', 'min');
        $maximum = FieldSettings::integer($definition->settings(), 'maxItems', 'max');
        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            throw new FieldValueException('INVALID_SCHEMA_RULE', 'Minimum item count cannot exceed maximum.');
        }
    }

    /** @return string|list<string> */
    public function normalize(mixed $value, FieldDefinition $definition, FieldContext $context): string|array
    {
        $allowed = FieldSettings::allowedValues($definition->settings());
        if (!$this->multiple) {
            if (!is_string($value) || !in_array($value, $allowed, true)) {
                throw new FieldValueException('NOT_ALLOWED', 'Value is not an allowed option.');
            }

            return $value;
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new FieldValueException('INVALID_TYPE', 'Value must be a list of options.');
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_string($item) || !in_array($item, $allowed, true)) {
                throw new FieldValueException('NOT_ALLOWED', 'List contains an option that is not allowed.');
            }

            $normalized[] = $item;
        }
        $normalized = array_values(array_unique($normalized));
        $minimum = FieldSettings::integer($definition->settings(), 'minItems', 'min');
        $maximum = FieldSettings::integer($definition->settings(), 'maxItems', 'max');
        if ($minimum !== null && count($normalized) < $minimum) {
            throw new FieldValueException('TOO_FEW_ITEMS', sprintf('Select at least %d options.', $minimum));
        }
        if ($maximum !== null && count($normalized) > $maximum) {
            throw new FieldValueException('TOO_MANY_ITEMS', sprintf('Select at most %d options.', $maximum));
        }

        return $normalized;
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
