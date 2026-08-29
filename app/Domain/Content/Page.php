<?php

declare(strict_types=1);

namespace FlatFileCms\Domain\Content;

use FlatFileCms\Infrastructure\Filesystem\FileRevision;

final readonly class Page
{
    /**
     * @param array<string, Slug> $slugs
     * @param array<string, string> $titles
     * @param array<string, mixed> $seo
     * @param list<array<string, mixed>> $blocks
     */
    public function __construct(
        private PageIdentity $identity,
        private bool $enabled,
        private ?string $layout,
        private array $slugs,
        private array $titles,
        private array $seo,
        private array $blocks,
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

    public function layout(): ?string
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

    /** @return list<array<string, mixed>> */
    public function blocks(): array
    {
        return $this->blocks;
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
