<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks\Field;

use FlatFileCms\Blocks\FieldDefinition;
use FlatFileCms\Blocks\FieldValueException;

final readonly class RepeaterFieldType implements FieldType
{
    public function name(): string
    {
        return 'repeater';
    }

    public function validateDefinition(FieldDefinition $definition): void
    {
        $minimum = FieldSettings::integer($definition->settings(), 'minItems', 'min');
        $maximum = FieldSettings::integer($definition->settings(), 'maxItems', 'max');
        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            throw new FieldValueException('INVALID_SCHEMA_RULE', 'Minimum item count cannot exceed maximum.');
        }
    }

    /** @return list<mixed> */
    public function normalize(mixed $value, FieldDefinition $definition, FieldContext $context): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new FieldValueException('INVALID_TYPE', 'Value must be a list.');
        }

        $minimum = FieldSettings::integer($definition->settings(), 'minItems', 'min');
        $maximum = FieldSettings::integer($definition->settings(), 'maxItems', 'max');
        if ($minimum !== null && count($value) < $minimum) {
            throw new FieldValueException('TOO_FEW_ITEMS', sprintf('List must contain at least %d items.', $minimum));
        }
        if ($maximum !== null && count($value) > $maximum) {
            throw new FieldValueException('TOO_MANY_ITEMS', sprintf('List may contain at most %d items.', $maximum));
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
