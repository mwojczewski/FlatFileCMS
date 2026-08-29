<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks;

final readonly class BlockDefinition
{
    /**
     * @param array<string, string> $name
     * @param array<string, string> $description
     * @param array<string, FieldDefinition> $fields
     */
    public function __construct(
        private string $type,
        private array $name,
        private array $description,
        private ?string $icon,
        private array $fields,
        private string $directory,
        private string $renderer,
        private int $modifiedAt,
    ) {}

    public function type(): string
    {
        return $this->type;
    }

    /** @return array<string, string> */
    public function name(): array
    {
        return $this->name;
    }

    /** @return array<string, string> */
    public function description(): array
    {
        return $this->description;
    }

    public function icon(): ?string
    {
        return $this->icon;
    }

    /** @return array<string, FieldDefinition> */
    public function fields(): array
    {
        return $this->fields;
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function renderer(): string
    {
        return $this->renderer;
    }

    public function modifiedAt(): int
    {
        return $this->modifiedAt;
    }
}
