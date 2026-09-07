<?php

declare(strict_types=1);

namespace FlatFileCms\Http;

use FlatFileCms\Blocks\BlockProcessor;
use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\ErrorPageRepository;
use FlatFileCms\Presentation\PageViewModel;
use FlatFileCms\Rendering\PageRenderer;
use FlatFileCms\Support\ContentData;

final readonly class WebErrorRenderer
{
    /** @var array<int, string> */
    private const array DESCRIPTIONS = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        413 => 'Content Too Large',
        415 => 'Unsupported Media Type',
        422 => 'Unprocessable Content',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];

    public function __construct(
        private LanguageRepository $languages,
        private ConfigurationRepository $configuration,
        private ErrorPageRepository $errors,
        private BlockProcessor $blocks,
        private PageRenderer $renderer,
    ) {}

    public function render(Request $request, int $status): ?Response
    {
        $languages = $this->languages->get();
        $page = $this->errors->find($status, $languages);
        if ($page === null || !$page->enabled()) {
            return null;
        }

        $locale = $this->locale($request, $languages->codes(), $languages->default());
        $configuration = $this->configuration->get();
        $site = ContentData::map($configuration->data()['site'] ?? null, 'site');
        $layout = $page->layout() ?? ContentData::string($site['defaultLayout'] ?? null, 'site.defaultLayout');
        $title = $page->title($locale, $languages->default());
        $description = self::DESCRIPTIONS[$status] ?? 'HTTP Error';
        $seo = [
            'title' => $title,
            'description' => $description,
            'canonical' => $request->path(),
            'robots' => ['index' => false, 'follow' => false],
            'openGraph' => ['title' => $title, 'description' => $description, 'url' => $request->path(), 'image' => null],
            'twitter' => ['title' => $title, 'description' => $description, 'image' => null],
            'jsonLd' => [],
        ];
        $view = new PageViewModel(
            $page->identity()->value(),
            $locale,
            $request->path(),
            $layout,
            $title,
            $seo,
            $this->blocks->forPublicPage($page, $locale, $languages),
            [],
        );
        $homepageUrl = $languages->isMultilingual() ? "/{$locale}/" : '/';
        $rendered = $this->renderer->render(
            $view,
            [],
            new ErrorView($status, $description, $homepageUrl),
        );
        $headers = $status >= 500
            ? ['Cache-Control' => 'no-store']
            : ['Cache-Control' => 'no-cache'];

        return Response::html($rendered->html(), $status, $headers);
    }

    /** @param list<string> $available */
    private function locale(Request $request, array $available, string $default): string
    {
        $firstSegment = explode('/', trim($request->path(), '/'))[0];

        return \in_array($firstSegment, $available, true) ? $firstSegment : $default;
    }
}
