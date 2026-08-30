<?php

declare(strict_types=1);

namespace FlatFileCms\Content;

final readonly class PageMetadata
{
    /**
     * @param array<string, string> $titles
     * @param array<string, string> $slugs
     * @param array<string, string> $seoTitles
     * @param array<string, string> $seoDescriptions
     */
    public function __construct(
        private bool $enabled,
        private ?string $layout,
        private array $titles,
        private array $slugs,
        private array $seoTitles,
        private array $seoDescriptions,
        private ?string $canonical,
        private bool $robotsIndex,
        private bool $robotsFollow,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function layout(): ?string
    {
        return $this->layout;
    }

    /** @return array<string, string> */
    public function titles(): array
    {
        return $this->titles;
    }

    /** @return array<string, string> */
    public function slugs(): array
    {
        return $this->slugs;
    }

    /** @return array<string, string> */
    public function seoTitles(): array
    {
        return $this->seoTitles;
    }

    /** @return array<string, string> */
    public function seoDescriptions(): array
    {
        return $this->seoDescriptions;
    }

    public function canonical(): ?string
    {
        return $this->canonical;
    }

    public function robotsIndex(): bool
    {
        return $this->robotsIndex;
    }

    public function robotsFollow(): bool
    {
        return $this->robotsFollow;
    }
}
