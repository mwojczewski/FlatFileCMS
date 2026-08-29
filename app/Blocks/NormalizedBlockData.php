<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks;

final readonly class NormalizedBlockData
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values) {}

    /** @return array<string, mixed> */
    public function values(): array
    {
        return $this->values;
    }
}
