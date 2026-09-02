<?php

declare(strict_types=1);

namespace FlatFileCms\Presentation;

final readonly class PageViewModel
{
    /**
     * @param array<string, mixed> $seo
     * @param list<array<string, mixed>> $blocks
     * @param array<string, string> $localizedUrls
     */
    public function __construct(
        private string $id,
        private string $locale,
        private string $url,
        private string $layout,
        private string $title,
        private array $seo,
        private array $blocks,
        private array $localizedUrls,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function layout(): string
    {
        return $this->layout;
    }

    public function title(): string
    {
        return $this->title;
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

    /** @return array<string, string> */
    public function localizedUrls(): array
    {
        return $this->localizedUrls;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'locale' => $this->locale,
            'url' => $this->url,
            'layout' => $this->layout,
            'title' => $this->title,
            'seo' => $this->seo,
            'blocks' => $this->blocks,
        ];
    }
}
