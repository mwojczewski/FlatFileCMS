<?php

declare(strict_types=1);

namespace FlatFileCms\Api;

use FlatFileCms\Blocks\BlockProcessor;
use FlatFileCms\Config\ConfigurationDocument;
use FlatFileCms\Content\PageRouteIndex;
use FlatFileCms\Domain\Content\Page;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Seo\SeoResolver;
use FlatFileCms\Support\ContentData;

final readonly class PageSerializer
{
    public function __construct(
        private BlockProcessor $blocks,
        private SeoResolver $seo,
    ) {}

    /** @return array<string, mixed> */
    public function serialize(
        Page $page,
        string $locale,
        LanguageConfig $languages,
        PageRouteIndex $routes,
        ConfigurationDocument $configuration,
    ): array {
        $url = $routes->urlFor($page->identity(), $locale);
        $layout = $page->layout() ?? $this->defaultLayout($configuration);

        return [
            'id' => $page->identity()->value(),
            'locale' => $locale,
            'url' => $url,
            'layout' => $layout,
            'title' => $page->title($locale, $languages->default()),
            'seo' => $this->seo->resolve($page, $locale, $url, $languages, $configuration),
            'blocks' => $this->blocks->forPublicPage($page, $locale, $languages),
        ];
    }

    public function blockDefinitionsModifiedAt(Page $page): int
    {
        return $this->blocks->definitionsModifiedAt($page);
    }

    private function defaultLayout(ConfigurationDocument $configuration): string
    {
        $site = ContentData::map($configuration->data()['site'] ?? null, 'site');

        return ContentData::string($site['defaultLayout'] ?? null, 'site.defaultLayout');
    }
}
