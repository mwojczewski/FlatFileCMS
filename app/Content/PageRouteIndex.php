<?php

declare(strict_types=1);

namespace FlatFileCms\Content;

use FlatFileCms\Domain\Content\Page;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Content\Slug;
use FlatFileCms\Domain\Localization\LanguageConfig;
use InvalidArgumentException;

final readonly class PageRouteIndex
{
    /**
     * @param array<string, Page> $pagesByIdentity
     * @param array<string, Page> $publicRoutes
     * @param array<string, string> $pathsByIdentityAndLocale
     */
    private function __construct(
        private LanguageConfig $languages,
        private array $pagesByIdentity,
        private array $publicRoutes,
        private array $pathsByIdentityAndLocale,
        private int $modifiedAt,
    ) {}

    /** @param list<Page> $pages */
    public static function build(array $pages, LanguageConfig $languages): self
    {
        $pagesByIdentity = [];
        $modifiedAt = 0;
        foreach ($pages as $page) {
            $identity = $page->identity()->value();
            if (isset($pagesByIdentity[$identity])) {
                throw new InvalidContentException(sprintf('Duplicate page identity "%s".', $identity));
            }

            $pagesByIdentity[$identity] = $page;
            $modifiedAt = max($modifiedAt, $page->modifiedAt());
        }

        $routes = [];
        $paths = [];
        foreach ($languages->codes() as $locale) {
            foreach ($pages as $page) {
                $path = self::localizedPath($page, $locale, $pagesByIdentity);
                $identityKey = self::identityLocaleKey($page->identity(), $locale);
                $paths[$identityKey] = $path;

                if (!$page->enabled()) {
                    continue;
                }

                $routeKey = self::routeKey($locale, $path);
                if (isset($routes[$routeKey])) {
                    throw new InvalidContentException(sprintf(
                        'Localized route collision for locale "%s" and path "%s".',
                        $locale,
                        $path,
                    ));
                }

                $routes[$routeKey] = $page;
            }
        }

        return new self($languages, $pagesByIdentity, $routes, $paths, $modifiedAt);
    }

    public function resolve(string $path, string $locale): Page
    {
        if (!$this->languages->has($locale)) {
            throw new PageNotFoundException('Page not found.');
        }

        $normalized = self::normalizePublicPath($path);
        $page = $this->publicRoutes[self::routeKey($locale, $normalized)] ?? null;
        if ($page === null) {
            throw new PageNotFoundException('Page not found.');
        }

        return $page;
    }

    public function page(PageIdentity $identity): Page
    {
        $page = $this->pagesByIdentity[$identity->value()] ?? null;
        if ($page === null || !$page->enabled()) {
            throw new PageNotFoundException('Page not found.');
        }

        return $page;
    }

    public function pathFor(PageIdentity $identity, string $locale): string
    {
        $path = $this->pathsByIdentityAndLocale[self::identityLocaleKey($identity, $locale)] ?? null;
        if ($path === null) {
            throw new PageNotFoundException('Page not found.');
        }

        return $path;
    }

    public function urlFor(PageIdentity $identity, string $locale): string
    {
        $this->page($identity);
        $path = $this->pathFor($identity, $locale);
        $prefix = $this->languages->isMultilingual() ? '/' . $locale : '';

        return $path === '' ? ($prefix === '' ? '/' : $prefix . '/') : $prefix . '/' . $path;
    }

    public function modifiedAt(): int
    {
        return $this->modifiedAt;
    }

    /**
     * @param array<string, Page> $pagesByIdentity
     */
    private static function localizedPath(Page $page, string $locale, array $pagesByIdentity): string
    {
        if ($page->identity()->isHomepage()) {
            return '';
        }

        $technicalSegments = array_map(
            static fn(Slug $segment): string => $segment->value(),
            $page->identity()->segments(),
        );
        $localizedSegments = [];
        $identitySegments = [];

        foreach ($technicalSegments as $technicalSegment) {
            $identitySegments[] = $technicalSegment;
            $ancestorIdentity = implode('/', $identitySegments);
            $ancestor = $pagesByIdentity[$ancestorIdentity] ?? null;
            $slug = $ancestor?->slug($locale);
            if ($ancestor === null || $slug === null) {
                throw new InvalidContentException(sprintf(
                    'Page "%s" requires a page ancestor and localized slug for "%s".',
                    $page->identity()->value(),
                    $locale,
                ));
            }

            $localizedSegments[] = $slug->value();
        }

        return implode('/', $localizedSegments);
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

    private static function routeKey(string $locale, string $path): string
    {
        return $locale . ':' . $path;
    }

    private static function identityLocaleKey(PageIdentity $identity, string $locale): string
    {
        return $identity->value() . ':' . $locale;
    }
}
