<?php

declare(strict_types=1);

namespace FlatFileCms\Collections;

final readonly class CollectionSettings
{
    /**
     * @param array<string, string> $slugs
     * @param array<string, string> $titles
     * @param array<string, string> $seoTitles
     * @param array<string, string> $seoDescriptions
     * @param list<array{parameter: string, field: string, allowedValues: list<string>}> $filters
     */
    public function __construct(
        public bool $enabled,
        public string $layout,
        public array $slugs,
        public array $titles,
        public array $seoTitles,
        public array $seoDescriptions,
        public ?string $canonical,
        public bool $robotsIndex,
        public bool $robotsFollow,
        public string $sortField,
        public string $sortDirection,
        public int $perPage,
        public array $filters,
    ) {}
}
