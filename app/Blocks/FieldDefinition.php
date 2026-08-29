<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks;

final readonly class FieldDefinition
{
    /**
     * @param array<string, mixed> $settings
     * @param array<string, FieldDefinition> $fields
     */
    public function __construct(
        private string $name,
        private string $type,
        private bool $required,
        private bool $translatable,
        private array $settings,
        private array $fields = [],
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function required(): bool
    {
        return $this->required;
    }

    public function translatable(): bool
    {
        return $this->translatable;
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        return $this->settings;
    }

    /** @return array<string, FieldDefinition> */
    public function fields(): array
    {
        return $this->fields;
    }
}
