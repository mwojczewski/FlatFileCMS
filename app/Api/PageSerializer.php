<?php

declare(strict_types=1);

namespace FlatFileCms\Api;

use FlatFileCms\Config\ConfigurationDocument;
use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Content\PageRouteIndex;
use FlatFileCms\Domain\Content\Page;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Domain\Localization\LocalizedDataResolver;
use FlatFileCms\Seo\SeoResolver;
use FlatFileCms\Support\ContentData;
use InvalidArgumentException;

final readonly class PageSerializer
{
    public function __construct(
        private LocalizedDataResolver $localization,
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
            'blocks' => $this->blocks($page, $locale, $languages),
        ];
    }

    private function defaultLayout(ConfigurationDocument $configuration): string
    {
        $site = ContentData::map($configuration->data()['site'] ?? null, 'site');

        return ContentData::string($site['defaultLayout'] ?? null, 'site.defaultLayout');
    }

    /** @return list<array<string, mixed>> */
    private function blocks(Page $page, string $locale, LanguageConfig $languages): array
    {
        try {
            $result = [];
            foreach ($page->blocks() as $index => $block) {
                $enabled = isset($block['enabled'])
                    ? ContentData::boolean($block['enabled'], 'blocks.' . $index . '.enabled')
                    : true;
                if (!$enabled) {
                    continue;
                }

                $data = ContentData::map($block['data'] ?? [], 'blocks.' . $index . '.data');
                $localizedData = $this->localization->resolve($data, $locale, $languages);

                $result[] = [
                    'id' => ContentData::string($block['id'] ?? null, 'blocks.' . $index . '.id'),
                    'type' => ContentData::string($block['type'] ?? null, 'blocks.' . $index . '.type'),
                    'data' => ContentData::map($localizedData, 'blocks.' . $index . '.data'),
                ];
            }

            return $result;
        } catch (InvalidArgumentException $exception) {
            throw new InvalidContentException(
                sprintf('Page "%s" contains invalid block data.', $page->identity()->value()),
                previous: $exception,
            );
        }
    }
}
