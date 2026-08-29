<?php

declare(strict_types=1);

namespace FlatFileCms\Api;

use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Config\LanguageDocument;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\PageNotFoundException;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Content\PageRouteIndex;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Domain\Localization\LocalizedDataResolver;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use FlatFileCms\Navigation\NavigationRepository;

final readonly class PublicApiController
{
    public function __construct(
        private LanguageRepository $languages,
        private ConfigurationRepository $configuration,
        private PageRepository $pages,
        private NavigationRepository $navigation,
        private LocalizedDataResolver $localization,
        private PageSerializer $pageSerializer,
        private ApiResponseFactory $responses,
    ) {}

    public function homepage(Request $request): Response
    {
        return $this->pageResponse($request, '');
    }

    public function page(Request $request): Response
    {
        return $this->pageResponse($request, (string) $request->attribute('path'));
    }

    public function navigation(Request $request): Response
    {
        [$languageDocument, $routes] = $this->routeContext();
        $languages = $languageDocument->config();
        $locale = $this->locale($request, $languages);
        $navigation = $this->navigation->resolve($locale, $languages, $routes);

        return $this->responses->cacheable(
            $request,
            [
                'locale' => $locale,
                'menus' => $navigation->menus(),
            ],
            max($languageDocument->modifiedAt(), $navigation->modifiedAt(), $routes->modifiedAt()),
        );
    }

    public function configuration(Request $request): Response
    {
        $languageDocument = $this->languages->document();
        $languages = $languageDocument->config();
        $locale = $this->locale($request, $languages);
        $configuration = $this->configuration->get();
        $projection = $this->configuration->publicProjection($configuration);
        $localized = $this->localization->resolve($projection, $locale, $languages);

        return $this->responses->cacheable(
            $request,
            [
                'locale' => $locale,
                'defaultLocale' => $languages->default(),
                'multilingual' => $languages->isMultilingual(),
                'languages' => $languages->languages(),
                'config' => is_array($localized) ? $localized : [],
            ],
            max($languageDocument->modifiedAt(), $configuration->modifiedAt()),
        );
    }

    private function pageResponse(Request $request, string $path): Response
    {
        [$languageDocument, $routes] = $this->routeContext();
        $configuration = $this->configuration->get();
        $languages = $languageDocument->config();
        $locale = $this->locale($request, $languages);

        try {
            $page = $routes->resolve($path, $locale);
        } catch (PageNotFoundException) {
            throw new HttpException(404, 'PAGE_NOT_FOUND', 'Page not found');
        }

        return $this->responses->cacheable(
            $request,
            $this->pageSerializer->serialize($page, $locale, $languages, $routes, $configuration),
            max(
                $page->modifiedAt(),
                $routes->modifiedAt(),
                $languageDocument->modifiedAt(),
                $configuration->modifiedAt(),
            ),
        );
    }

    /** @return array{LanguageDocument, PageRouteIndex} */
    private function routeContext(): array
    {
        $languageDocument = $this->languages->document();
        $pages = $this->pages->all($languageDocument->config());
        $routes = PageRouteIndex::build($pages, $languageDocument->config());

        return [$languageDocument, $routes];
    }

    private function locale(Request $request, LanguageConfig $languages): string
    {
        $value = $request->query()['lang'] ?? $languages->default();
        if (!is_string($value) || !$languages->has($value)) {
            throw new HttpException(400, 'LANGUAGE_NOT_AVAILABLE', 'Language is not available');
        }

        return $value;
    }
}
