<?php

declare(strict_types=1);

namespace FlatFileCms\Seo;

use FlatFileCms\Collections\Collection;
use FlatFileCms\Config\ConfigurationDocument;
use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Domain\Content\Page;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Domain\Localization\LocalizedDataResolver;

final readonly class SeoResolver
{
    public function __construct(private LocalizedDataResolver $localization) {}

    /** @return array<string, mixed> */
    public function resolve(
        Page $page,
        string $locale,
        string $publicUrl,
        LanguageConfig $languages,
        ConfigurationDocument $configuration,
    ): array {
        return $this->resolveData(
            $page->title($locale, $languages->default()),
            $page->seo(),
            $locale,
            $publicUrl,
            $languages,
            $configuration,
        );
    }

    /** @return array<string, mixed> */
    public function resolveCollection(
        Collection $collection,
        string $locale,
        string $publicUrl,
        LanguageConfig $languages,
        ConfigurationDocument $configuration,
    ): array {
        return $this->resolveData(
            $collection->title($locale, $languages->default()),
            $collection->seo(),
            $locale,
            $publicUrl,
            $languages,
            $configuration,
        );
    }

    /**
     * @param array<string, mixed> $resourceSeo
     * @return array<string, mixed>
     */
    private function resolveData(
        string $resourceTitle,
        array $resourceSeo,
        string $locale,
        string $publicUrl,
        LanguageConfig $languages,
        ConfigurationDocument $configuration,
    ): array {
        $setup = $configuration->data();
        $global = $this->map($this->localization->resolve($setup['seo'] ?? [], $locale, $languages));
        $pageSeo = $this->map($this->localization->resolve($resourceSeo, $locale, $languages));
        $site = $this->map($this->localization->resolve($setup['site'] ?? [], $locale, $languages));

        $title = $this->optionalString($pageSeo['title'] ?? null)
            ?? $resourceTitle;
        $suffix = $this->optionalString($global['titleSuffix'] ?? null);
        $fullTitle = $suffix === null || str_ends_with($title, $suffix) ? $title : $title . ' — ' . $suffix;
        $description = $this->optionalString($pageSeo['description'] ?? null)
            ?? $this->optionalString($global['description'] ?? null)
            ?? '';
        $baseUrl = $this->optionalString($site['url'] ?? null);
        $canonical = $this->canonical(
            $this->optionalString($pageSeo['canonical'] ?? null),
            $baseUrl,
            $publicUrl,
        );

        $robots = $this->map($pageSeo['robots'] ?? []);
        $openGraph = [...$this->map($global['openGraph'] ?? []), ...$this->map($pageSeo['openGraph'] ?? [])];
        $openGraph['title'] ??= $fullTitle;
        $openGraph['description'] ??= $description;
        $openGraph['url'] ??= $canonical;
        $openGraph['image'] ??= $global['ogImage'] ?? null;

        $twitter = [...$this->map($global['twitter'] ?? []), ...$this->map($pageSeo['twitter'] ?? [])];
        $twitter['title'] ??= $fullTitle;
        $twitter['description'] ??= $description;
        $twitter['image'] ??= $openGraph['image'] ?? null;

        return [
            'title' => $fullTitle,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => [
                'index' => $this->boolean($robots['index'] ?? true),
                'follow' => $this->boolean($robots['follow'] ?? true),
            ],
            'openGraph' => $openGraph,
            'twitter' => $twitter,
            'jsonLd' => $pageSeo['jsonLd'] ?? $global['jsonLd'] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    private function map(mixed $value): array
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new InvalidContentException('SEO sections must be mappings.');
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new InvalidContentException('SEO mapping keys must be strings.');
            }

            $result[$key] = $item;
        }

        return $result;
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidContentException('SEO text values must be strings.');
        }

        return $value;
    }

    private function boolean(mixed $value): bool
    {
        if (!is_bool($value)) {
            throw new InvalidContentException('SEO robots values must be boolean.');
        }

        return $value;
    }

    private function absoluteUrl(?string $baseUrl, string $path): string
    {
        return $baseUrl === null ? $path : rtrim($baseUrl, '/') . $path;
    }

    private function canonical(?string $configured, ?string $baseUrl, string $publicUrl): string
    {
        if ($configured === null) {
            return $this->absoluteUrl($baseUrl, $publicUrl);
        }

        if (str_starts_with($configured, '/')) {
            return $this->absoluteUrl($baseUrl, $configured);
        }

        if (
            filter_var($configured, FILTER_VALIDATE_URL) === false
            || !in_array(parse_url($configured, PHP_URL_SCHEME), ['http', 'https'], true)
        ) {
            throw new InvalidContentException('SEO canonical URL is invalid.');
        }

        return $configured;
    }
}
