<?php

declare(strict_types=1);

namespace FlatFileCms\Presentation;

use FlatFileCms\Collections\CollectionResult;
use FlatFileCms\Config\ConfigurationDocument;
use FlatFileCms\Content\PageRouteIndex;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Domain\Localization\LocalizedDataResolver;
use FlatFileCms\Seo\SeoResolver;

final readonly class CollectionViewModelFactory
{
    public function __construct(
        private LocalizedDataResolver $localization,
        private SeoResolver $seo,
    ) {}

    public function create(
        CollectionResult $result,
        string $locale,
        LanguageConfig $languages,
        PageRouteIndex $routes,
        ConfigurationDocument $configuration,
    ): CollectionViewModel {
        $collection = $result->collection();
        $url = $routes->collectionUrlFor($collection->identity(), $locale);
        $items = [];
        foreach ($result->items() as $page) {
            $attributes = $this->localization->resolve($page->attributes(), $locale, $languages);
            $items[] = [
                'id' => $page->identity()->value(),
                'url' => $routes->urlFor($page->identity(), $locale),
                'title' => $page->title($locale, $languages->default()),
                'attributes' => \is_array($attributes) ? $attributes : [],
            ];
        }

        return new CollectionViewModel(
            [
                'id' => $collection->identity()->value(),
                'locale' => $locale,
                'url' => $url,
                'layout' => $collection->layout(),
                'title' => $collection->title($locale, $languages->default()),
                'seo' => $this->seo->resolveCollection(
                    $collection,
                    $locale,
                    $url,
                    $languages,
                    $configuration,
                ),
            ],
            $items,
            [
                'page' => $result->page(),
                'perPage' => $result->perPage(),
                'totalItems' => $result->totalItems(),
                'totalPages' => $result->totalPages(),
            ],
            $result->activeFilters(),
            $this->localizedUrls($collection->identity(), $languages, $routes),
        );
    }

    /** @return array<string, string> */
    private function localizedUrls(
        PageIdentity $identity,
        LanguageConfig $languages,
        PageRouteIndex $routes,
    ): array {
        $urls = [];
        foreach ($languages->codes() as $locale) {
            $urls[$locale] = $routes->collectionUrlFor($identity, $locale);
        }

        return $urls;
    }
}
