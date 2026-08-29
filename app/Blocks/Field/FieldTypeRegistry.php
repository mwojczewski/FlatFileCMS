<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks\Field;

use FlatFileCms\Blocks\InvalidBlockDefinitionException;

final class FieldTypeRegistry
{
    /** @var array<string, FieldType> */
    private array $types = [];

    public function register(FieldType $type): void
    {
        $name = $type->name();
        if (preg_match('/^[a-z][a-z0-9_-]*$/D', $name) !== 1) {
            throw new InvalidBlockDefinitionException('Field type name is invalid.');
        }
        if (isset($this->types[$name])) {
            throw new InvalidBlockDefinitionException(sprintf('Field type "%s" is already registered.', $name));
        }

        $this->types[$name] = $type;
    }

    public function get(string $name): FieldType
    {
        return $this->types[$name]
            ?? throw new InvalidBlockDefinitionException(sprintf('Unknown field type "%s".', $name));
    }

    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }

    /** @return list<string> */
    public function names(): array
    {
        $names = array_keys($this->types);
        sort($names);

        return $names;
    }
}
