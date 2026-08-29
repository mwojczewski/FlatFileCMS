<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks\Field;

use FlatFileCms\Blocks\FieldDefinition;

interface FieldType
{
    public function name(): string;

    public function validateDefinition(FieldDefinition $definition): void;

    public function normalize(mixed $value, FieldDefinition $definition, FieldContext $context): mixed;

    public function localize(
        mixed $value,
        string $locale,
        FieldDefinition $definition,
        FieldContext $context,
    ): mixed;
}
