<?php

declare(strict_types=1);

namespace FlatFileCms\Collections;

use FlatFileCms\Domain\Content\Page;

final readonly class CollectionResult
{
    /**
     * @param list<Page> $items
     * @param array<string, string> $activeFilters
     */
    public function __construct(
        private Collection $collection,
        private array $items,
        private int $page,
        private int $perPage,
        private int $totalItems,
        private int $totalPages,
        private array $activeFilters,
        private int $modifiedAt,
    ) {}

    public function collection(): Collection
    {
        return $this->collection;
    }

    /** @return list<Page> */
    public function items(): array
    {
        return $this->items;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function totalItems(): int
    {
        return $this->totalItems;
    }

    public function totalPages(): int
    {
        return $this->totalPages;
    }

    /** @return array<string, string> */
    public function activeFilters(): array
    {
        return $this->activeFilters;
    }

    public function modifiedAt(): int
    {
        return $this->modifiedAt;
    }
}
