<?php

declare(strict_types=1);

namespace FlatFileCms\Presentation;

final readonly class CollectionViewModel
{
    /**
     * @param array<string, mixed> $collection
     * @param list<array<string, mixed>> $items
     * @param array<string, int> $pagination
     * @param array<string, string> $filters
     */
    public function __construct(
        private array $collection,
        private array $items,
        private array $pagination,
        private array $filters,
    ) {}

    /** @return array<string, mixed> */
    public function collection(): array
    {
        return $this->collection;
    }

    /** @return list<array<string, mixed>> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return array<string, int> */
    public function pagination(): array
    {
        return $this->pagination;
    }

    /** @return array<string, string> */
    public function filters(): array
    {
        return $this->filters;
    }

    public function locale(): string
    {
        $locale = $this->collection['locale'] ?? '';

        return \is_string($locale) ? $locale : '';
    }

    public function url(): string
    {
        $url = $this->collection['url'] ?? '';

        return \is_string($url) ? $url : '';
    }

    public function layout(): string
    {
        $layout = $this->collection['layout'] ?? '';

        return \is_string($layout) ? $layout : '';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'collection' => $this->collection,
            'items' => $this->items,
            'pagination' => $this->pagination,
            'filters' => $this->filters,
        ];
    }
}
