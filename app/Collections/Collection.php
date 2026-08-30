<?php

declare(strict_types=1);

namespace FlatFileCms\Collections;

use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Content\Slug;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;

final readonly class Collection
{
    /**
     * @param array<string, Slug> $slugs
     * @param array<string, string> $titles
     * @param array<string, mixed> $seo
     * @param list<CollectionFilter> $filters
     */
    public function __construct(
        private PageIdentity $identity,
        private bool $enabled,
        private string $layout,
        private array $slugs,
        private array $titles,
        private array $seo,
        private string $sortField,
        private string $sortDirection,
        private int $perPage,
        private array $filters,
        private FileRevision $revision,
        private int $modifiedAt,
    ) {}

    public function identity(): PageIdentity
    {
        return $this->identity;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function layout(): string
    {
        return $this->layout;
    }

    public function slug(string $locale): ?Slug
    {
        return $this->slugs[$locale] ?? null;
    }

    public function title(string $locale, string $fallback): string
    {
        return $this->titles[$locale] ?? $this->titles[$fallback] ?? '';
    }

    /** @return array<string, mixed> */
    public function seo(): array
    {
        return $this->seo;
    }

    public function sortField(): string
    {
        return $this->sortField;
    }

    public function sortDirection(): string
    {
        return $this->sortDirection;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    /** @return list<CollectionFilter> */
    public function filters(): array
    {
        return $this->filters;
    }

    public function revision(): FileRevision
    {
        return $this->revision;
    }

    public function modifiedAt(): int
    {
        return $this->modifiedAt;
    }
}
