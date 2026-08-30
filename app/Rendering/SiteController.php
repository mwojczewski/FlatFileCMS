<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

use FlatFileCms\Collections\CollectionNotFoundException;
use FlatFileCms\Collections\CollectionRepository;
use FlatFileCms\Collections\CollectionService;
use FlatFileCms\Collections\InvalidCollectionQueryException;
use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\PageNotFoundException;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Content\PageRouteIndex;
use FlatFileCms\Domain\Content\Slug;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Http\HtmlResponseFactory;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use FlatFileCms\Navigation\NavigationRepository;
use FlatFileCms\Presentation\CollectionViewModelFactory;
use FlatFileCms\Presentation\PageViewModelFactory;
use InvalidArgumentException;

final readonly class SiteController
{
    public function __construct(
        private LanguageRepository $languages,
        private ConfigurationRepository $configuration,
        private PageRepository $pages,
        private CollectionRepository $collections,
        private CollectionService $collectionService,
        private NavigationRepository $navigation,
        private PageViewModelFactory $pageViews,
        private CollectionViewModelFactory $collectionViews,
        private PageRenderer $renderer,
        private CollectionRenderer $collectionRenderer,
        private HtmlResponseFactory $responses,
    ) {}

    public function homepage(Request $request): Response
    {
        return $this->show($request, '');
    }

    public function page(Request $request): Response
    {
        return $this->show($request, (string) $request->attribute('path'));
    }

    private function show(Request $request, string $requestedPath): Response
    {
        $languageDocument = $this->languages->document();
        $languages = $languageDocument->config();
        [$locale, $contentPath, $redirect] = $this->route($requestedPath, $languages);
        if ($redirect !== null) {
            return Response::redirect($redirect);
        }

        $pages = $this->pages->all($languages);
        $collections = $this->collections->all($languages);
        $routes = PageRouteIndex::build($pages, $languages, $collections);
        $configuration = $this->configuration->get();
        $navigation = $this->navigation->resolve($locale, $languages, $routes);

        try {
            $page = $routes->resolve($contentPath, $locale);
        } catch (PageNotFoundException) {
            try {
                $collection = $routes->resolveCollection($contentPath, $locale);
                $result = $this->collectionService->query(
                    $collection,
                    $pages,
                    $request->query(),
                    $locale,
                    $languages,
                );
            } catch (CollectionNotFoundException) {
                throw new HttpException(404, 'PAGE_NOT_FOUND', 'Page not found');
            } catch (InvalidCollectionQueryException $exception) {
                throw new HttpException(400, 'INVALID_COLLECTION_QUERY', $exception->getMessage());
            }

            $view = $this->collectionViews->create($result, $locale, $languages, $routes, $configuration);
            $rendered = $this->collectionRenderer->render($view, $navigation->menus());

            return $this->responses->cacheable(
                $request,
                $rendered->html(),
                max(
                    $result->modifiedAt(),
                    $rendered->assetsModifiedAt(),
                    $routes->modifiedAt(),
                    $languageDocument->modifiedAt(),
                    $configuration->modifiedAt(),
                    $navigation->modifiedAt(),
                ),
            );
        }

        $view = $this->pageViews->create($page, $locale, $languages, $routes, $configuration);
        $rendered = $this->renderer->render($view, $navigation->menus());

        return $this->responses->cacheable(
            $request,
            $rendered->html(),
            max(
                $page->modifiedAt(),
                $this->pageViews->blockDefinitionsModifiedAt($page),
                $rendered->assetsModifiedAt(),
                $routes->modifiedAt(),
                $languageDocument->modifiedAt(),
                $configuration->modifiedAt(),
                $navigation->modifiedAt(),
            ),
        );
    }

    /** @return array{string, string, ?string} */
    private function route(string $requestedPath, LanguageConfig $languages): array
    {
        $path = trim($requestedPath, '/');
        if (!$languages->isMultilingual()) {
            return [$languages->default(), $this->safeContentPath($path), null];
        }

        if ($path === '') {
            return [$languages->default(), '', '/' . $languages->default() . '/'];
        }

        $segments = explode('/', $path);
        $candidate = $segments[0];
        if (!$languages->has($candidate)) {
            $path = $this->safeContentPath($path);

            return [$languages->default(), $path, '/' . $languages->default() . '/' . $path];
        }

        array_shift($segments);

        return [$candidate, $this->safeContentPath(implode('/', $segments)), null];
    }

    private function safeContentPath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        try {
            return implode('/', array_map(
                static fn(string $segment): string => Slug::fromString($segment)->value(),
                explode('/', $path),
            ));
        } catch (InvalidArgumentException) {
            throw new HttpException(404, 'PAGE_NOT_FOUND', 'Page not found');
        }
    }
}
