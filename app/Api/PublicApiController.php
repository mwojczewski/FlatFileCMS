<?php

declare(strict_types=1);

namespace FlatFileCms\Api;

use FlatFileCms\Collections\CollectionNotFoundException;
use FlatFileCms\Collections\CollectionRepository;
use FlatFileCms\Collections\CollectionService;
use FlatFileCms\Collections\InvalidCollectionQueryException;
use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Config\LanguageDocument;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\PageNotFoundException;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Content\PageRouteIndex;
use FlatFileCms\Domain\Content\Page;
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
        private CollectionRepository $collections,
        private CollectionService $collectionService,
        private NavigationRepository $navigation,
        private LocalizedDataResolver $localization,
        private PageSerializer $pageSerializer,
        private CollectionSerializer $collectionSerializer,
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

    public function collection(Request $request): Response
    {
        [$languageDocument, $routes, $pages] = $this->routeContext();
        $languages = $languageDocument->config();
        $locale = $this->locale($request, $languages);
        $configuration = $this->configuration->get();

        try {
            $collection = $routes->resolveCollection((string) $request->attribute('path'), $locale);
            $result = $this->collectionService->query(
                $collection,
                $pages,
                $request->query(),
                $locale,
                $languages,
            );
        } catch (CollectionNotFoundException) {
            throw new HttpException(404, 'COLLECTION_NOT_FOUND', 'Collection not found');
        } catch (InvalidCollectionQueryException $exception) {
            throw new HttpException(400, 'INVALID_COLLECTION_QUERY', $exception->getMessage());
        }

        return $this->responses->cacheable(
            $request,
            $this->collectionSerializer->serialize($result, $locale, $languages, $routes, $configuration),
            max(
                $result->modifiedAt(),
                $routes->modifiedAt(),
                $languageDocument->modifiedAt(),
                $configuration->modifiedAt(),
            ),
        );
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
                'config' => \is_array($localized) ? $localized : [],
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
                $this->pageSerializer->blockDefinitionsModifiedAt($page),
                $this->pageSerializer->mediaModifiedAt($page),
                $routes->modifiedAt(),
                $languageDocument->modifiedAt(),
                $configuration->modifiedAt(),
            ),
        );
    }

    /** @return array{LanguageDocument, PageRouteIndex, list<Page>} */
    private function routeContext(): array
    {
        $languageDocument = $this->languages->document();
        $pages = $this->pages->all($languageDocument->config());
        $collections = $this->collections->all($languageDocument->config());
        $routes = PageRouteIndex::build($pages, $languageDocument->config(), $collections);

        return [$languageDocument, $routes, $pages];
    }

    private function locale(Request $request, LanguageConfig $languages): string
    {
        $value = $request->query()['lang'] ?? $languages->default();
        if (!\is_string($value) || !$languages->has($value)) {
            throw new HttpException(400, 'LANGUAGE_NOT_AVAILABLE', 'Language is not available');
        }

        return $value;
    }
}
