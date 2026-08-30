<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

use FlatFileCms\Presentation\CollectionViewModel;
use FlatFileCms\Support\ContentData;

final readonly class CollectionRenderer
{
    public function __construct(
        private LayoutRegistry $layouts,
        private OutputBuffer $buffer,
        private MarkdownRenderer $markdown,
        private PartialRenderer $partials,
    ) {}

    /** @param array<string, list<array<string, mixed>>> $navigation */
    public function render(CollectionViewModel $view, array $navigation): RenderedPage
    {
        $context = new RenderContext(
            $view->locale(),
            $view->url(),
            $this->markdown,
            $this->partials,
        );
        $template = $this->layouts->get($view->layout());
        $collection = $view->collection();
        $items = $view->items();
        $pagination = $view->pagination();
        $filters = $view->filters();
        $seo = ContentData::map($collection['seo'] ?? null, 'collection.seo');
        $assets = new AssetCollection([], [], 0);

        $html = $this->buffer->capture(
            static function () use (
                $template,
                $collection,
                $items,
                $pagination,
                $filters,
                $seo,
                $navigation,
                $assets,
                $context,
            ): void {
                require $template;
            },
        );

        return new RenderedPage(
            $html,
            max($this->layouts->modifiedAt($view->layout()), $this->partials->modifiedAt()),
        );
    }
}
