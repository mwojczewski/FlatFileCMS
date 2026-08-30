<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

final readonly class AssetCollection
{
    /**
     * @param list<string> $styles
     * @param list<string> $scripts
     */
    public function __construct(
        private array $styles,
        private array $scripts,
        private int $modifiedAt,
    ) {}

    /** @return list<string> */
    public function styles(): array
    {
        return $this->styles;
    }

    /** @return list<string> */
    public function scripts(): array
    {
        return $this->scripts;
    }

    public function modifiedAt(): int
    {
        return $this->modifiedAt;
    }
}
