<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks;

use FlatFileCms\Blocks\Field\BooleanFieldType;
use FlatFileCms\Blocks\Field\ChoiceFieldType;
use FlatFileCms\Blocks\Field\FieldTypeRegistry;
use FlatFileCms\Blocks\Field\FormattedStringFieldType;
use FlatFileCms\Blocks\Field\MediaFieldType;
use FlatFileCms\Blocks\Field\NumberFieldType;
use FlatFileCms\Blocks\Field\RepeaterFieldType;
use FlatFileCms\Blocks\Field\TextFieldType;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;

final class BuiltinFieldTypes
{
    public static function create(SafePathResolver $paths): FieldTypeRegistry
    {
        $registry = new FieldTypeRegistry();
        foreach (['text', 'textarea', 'markdown'] as $name) {
            $registry->register(new TextFieldType($name));
        }
        $registry->register(new NumberFieldType());
        $registry->register(new BooleanFieldType());
        $registry->register(new ChoiceFieldType('select', false));
        $registry->register(new ChoiceFieldType('multiselect', true));
        $registry->register(new MediaFieldType('image', $paths));
        $registry->register(new MediaFieldType('file', $paths));
        foreach (['url', 'email', 'date', 'datetime', 'color'] as $name) {
            $registry->register(new FormattedStringFieldType($name));
        }
        $registry->register(new RepeaterFieldType());

        return $registry;
    }
}
