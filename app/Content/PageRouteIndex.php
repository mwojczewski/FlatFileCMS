<?php

declare(strict_types=1);

namespace FlatFileCms\Content;

use FlatFileCms\Collections\Collection;
use FlatFileCms\Collections\CollectionNotFoundException;
use FlatFileCms\Domain\Content\Page;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Content\Slug;
use FlatFileCms\Domain\Localization\LanguageConfig;
use InvalidArgumentException;

final readonly class PageRouteIndex
{
    /**
     * @param array<string, Page> $pagesByIdentity
     * @param array<string, Collection> $collectionsByIdentity
     * @param array<string, Page> $publicPageRoutes
     * @param array<string, Collection> $publicCollectionRoutes
     * @param array<string, string> $pagePaths
     * @param array<string, string> $collectionPaths
     */
    private function __construct(
        private LanguageConfig $languages,
        private array $pagesByIdentity,
        private array $collectionsByIdentity,
        private array $publicPageRoutes,
        private array $publicCollectionRoutes,
        private array $pagePaths,
        private array $collectionPaths,
        private int $modifiedAt,
    ) {}

    /**
     * @param list<Page> $pages
     * @param list<Collection> $collections
     */
    public static function build(array $pages, LanguageConfig $languages, array $collections = []): self
    {
        $pagesByIdentity = [];
        $collectionsByIdentity = [];
        $modifiedAt = 0;

        foreach ($pages as $page) {
            $identity = $page->identity()->value();
            if (isset($pagesByIdentity[$identity])) {
                throw new InvalidContentException(sprintf('Duplicate page identity "%s".', $identity));
            }
            $pagesByIdentity[$identity] = $page;
            $modifiedAt = max($modifiedAt, $page->modifiedAt());
        }
        foreach ($collections as $collection) {
            $identity = $collection->identity()->value();
            if (isset($collectionsByIdentity[$identity]) || isset($pagesByIdentity[$identity])) {
                throw new InvalidContentException(sprintf('Duplicate content identity "%s".', $identity));
            }
            $collectionsByIdentity[$identity] = $collection;
            $modifiedAt = max($modifiedAt, $collection->modifiedAt());
        }

        $pageRoutes = [];
        $collectionRoutes = [];
        $pagePaths = [];
        $collectionPaths = [];
        foreach ($languages->codes() as $locale) {
            foreach ($collections as $collection) {
                $path = self::localizedPath(
                    $collection->identity(),
                    $locale,
                    $pagesByIdentity,
                    $collectionsByIdentity,
                );
                $collectionPaths[self::identityLocaleKey($collection->identity(), $locale)] = $path;
                if (!$collection->enabled()) {
                    continue;
                }

                $routeKey = self::routeKey($locale, $path);
                self::assertRouteAvailable($routeKey, $locale, $path, $pageRoutes, $collectionRoutes);
                $collectionRoutes[$routeKey] = $collection;
            }

            foreach ($pages as $page) {
                $path = self::localizedPath($page->identity(), $locale, $pagesByIdentity, $collectionsByIdentity);
                $pagePaths[self::identityLocaleKey($page->identity(), $locale)] = $path;
                if (!$page->enabled()) {
                    continue;
                }

                $routeKey = self::routeKey($locale, $path);
                self::assertRouteAvailable($routeKey, $locale, $path, $pageRoutes, $collectionRoutes);
                $pageRoutes[$routeKey] = $page;
            }
        }

        return new self(
            $languages,
            $pagesByIdentity,
            $collectionsByIdentity,
            $pageRoutes,
            $collectionRoutes,
            $pagePaths,
            $collectionPaths,
            $modifiedAt,
        );
    }

    public function resolve(string $path, string $locale): Page
    {
        if (!$this->languages->has($locale)) {
            throw new PageNotFoundException('Page not found.');
        }

        $page = $this->publicPageRoutes[self::routeKey($locale, self::normalizePublicPath($path))] ?? null;
        if ($page === null) {
            throw new PageNotFoundException('Page not found.');
        }

        return $page;
    }

    public function resolveCollection(string $path, string $locale): Collection
    {
        if (!$this->languages->has($locale)) {
            throw new CollectionNotFoundException('Collection not found.');
        }

        try {
            $normalized = self::normalizePublicPath($path);
        } catch (PageNotFoundException) {
            throw new CollectionNotFoundException('Collection not found.');
        }
        $collection = $this->publicCollectionRoutes[self::routeKey($locale, $normalized)] ?? null;
        if ($collection === null) {
            throw new CollectionNotFoundException('Collection not found.');
        }

        return $collection;
    }

    public function page(PageIdentity $identity): Page
    {
        $page = $this->pagesByIdentity[$identity->value()] ?? null;
        if ($page === null || !$page->enabled()) {
            throw new PageNotFoundException('Page not found.');
        }

        return $page;
    }

    public function collection(PageIdentity $identity): Collection
    {
        $collection = $this->collectionsByIdentity[$identity->value()] ?? null;
        if ($collection === null || !$collection->enabled()) {
            throw new CollectionNotFoundException('Collection not found.');
        }

        return $collection;
    }

    public function pathFor(PageIdentity $identity, string $locale): string
    {
        $path = $this->pagePaths[self::identityLocaleKey($identity, $locale)] ?? null;
        if ($path === null) {
            throw new PageNotFoundException('Page not found.');
        }

        return $path;
    }

    public function collectionPathFor(PageIdentity $identity, string $locale): string
    {
        $path = $this->collectionPaths[self::identityLocaleKey($identity, $locale)] ?? null;
        if ($path === null) {
            throw new CollectionNotFoundException('Collection not found.');
        }

        return $path;
    }

    public function urlFor(PageIdentity $identity, string $locale): string
    {
        $this->page($identity);

        return $this->publicUrl($this->pathFor($identity, $locale), $locale);
    }

    public function collectionUrlFor(PageIdentity $identity, string $locale): string
    {
        $this->collection($identity);

        return $this->publicUrl($this->collectionPathFor($identity, $locale), $locale);
    }

    public function modifiedAt(): int
    {
        return $this->modifiedAt;
    }

    /**
     * @param array<string, Page> $pages
     * @param array<string, Collection> $collections
     */
    private static function localizedPath(
        PageIdentity $identity,
        string $locale,
        array $pages,
        array $collections,
    ): string {
        if ($identity->isHomepage()) {
            return '';
        }

        $localizedSegments = [];
        $identitySegments = [];
        foreach ($identity->segments() as $segment) {
            $identitySegments[] = $segment->value();
            $ancestorIdentity = implode('/', $identitySegments);
            $ancestor = $pages[$ancestorIdentity] ?? $collections[$ancestorIdentity] ?? null;
            $slug = $ancestor?->slug($locale);
            if ($slug === null) {
                throw new InvalidContentException(sprintf(
                    'Content "%s" requires an ancestor with a localized slug for "%s".',
                    $identity->value(),
                    $locale,
                ));
            }

            $localizedSegments[] = $slug->value();
        }

        return implode('/', $localizedSegments);
    }

    /**
     * @param array<string, Page> $pageRoutes
     * @param array<string, Collection> $collectionRoutes
     */
    private static function assertRouteAvailable(
        string $routeKey,
        string $locale,
        string $path,
        array $pageRoutes,
        array $collectionRoutes,
    ): void {
        if (isset($pageRoutes[$routeKey]) || isset($collectionRoutes[$routeKey])) {
            throw new InvalidContentException(sprintf(
                'Localized route collision for locale "%s" and path "%s".',
                $locale,
                $path,
            ));
        }
    }

    private static function normalizePublicPath(string $path): string
    {
        $path = trim($path, '/');
        if ($path === '') {
            return '';
        }

        try {
            return implode('/', array_map(
                static fn(string $segment): string => Slug::fromString($segment)->value(),
                explode('/', $path),
            ));
        } catch (InvalidArgumentException) {
            throw new PageNotFoundException('Page not found.');
        }
    }

    private function publicUrl(string $path, string $locale): string
    {
        $prefix = $this->languages->isMultilingual() ? '/' . $locale : '';

        return $path === '' ? ($prefix === '' ? '/' : $prefix . '/') : $prefix . '/' . $path;
    }

    private static function routeKey(string $locale, string $path): string
    {
        return $locale . ':' . $path;
    }

    private static function identityLocaleKey(PageIdentity $identity, string $locale): string
    {
        return $identity->value() . ':' . $locale;
    }
}
