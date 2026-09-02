<?php

declare(strict_types=1);

namespace FlatFileCms\Presentation;

use FlatFileCms\Blocks\BlockProcessor;
use FlatFileCms\Config\ConfigurationDocument;
use FlatFileCms\Content\PageRouteIndex;
use FlatFileCms\Domain\Content\Page;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Media\MediaOutputEnricher;
use FlatFileCms\Seo\SeoResolver;
use FlatFileCms\Support\ContentData;

final readonly class PageViewModelFactory
{
    public function __construct(
        private BlockProcessor $blocks,
        private SeoResolver $seo,
        private MediaOutputEnricher $media,
    ) {}

    public function create(
        Page $page,
        string $locale,
        LanguageConfig $languages,
        PageRouteIndex $routes,
        ConfigurationDocument $configuration,
    ): PageViewModel {
        $url = $routes->urlFor($page->identity(), $locale);

        return new PageViewModel(
            $page->identity()->value(),
            $locale,
            $url,
            $page->layout() ?? $this->defaultLayout($configuration),
            $page->title($locale, $languages->default()),
            $this->seo->resolve($page, $locale, $url, $languages, $configuration),
            $this->media->enrich(
                $page->identity(),
                $this->blocks->forPublicPage($page, $locale, $languages),
            ),
            $this->localizedUrls($page, $languages, $routes),
        );
    }

    /** @return array<string, string> */
    private function localizedUrls(Page $page, LanguageConfig $languages, PageRouteIndex $routes): array
    {
        $urls = [];
        foreach ($languages->codes() as $locale) {
            $urls[$locale] = $routes->urlFor($page->identity(), $locale);
        }

        return $urls;
    }

    public function blockDefinitionsModifiedAt(Page $page): int
    {
        return $this->blocks->definitionsModifiedAt($page);
    }

    public function mediaModifiedAt(Page $page): int
    {
        return $this->media->modifiedAt($page->identity());
    }

    private function defaultLayout(ConfigurationDocument $configuration): string
    {
        $site = ContentData::map($configuration->data()['site'] ?? null, 'site');

        return ContentData::string($site['defaultLayout'] ?? null, 'site.defaultLayout');
    }
}
