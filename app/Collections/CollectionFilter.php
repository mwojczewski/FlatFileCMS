<?php

declare(strict_types=1);

namespace FlatFileCms\Collections;

final readonly class CollectionFilter
{
    /** @param list<string> $allowedValues */
    public function __construct(
        private string $parameter,
        private string $field,
        private array $allowedValues,
    ) {}

    public function parameter(): string
    {
        return $this->parameter;
    }

    public function field(): string
    {
        return $this->field;
    }

    /** @return list<string> */
    public function allowedValues(): array
    {
        return $this->allowedValues;
    }
}
