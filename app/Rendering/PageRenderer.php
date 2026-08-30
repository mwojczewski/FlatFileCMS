<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

use FlatFileCms\Presentation\PageViewModel;
use FlatFileCms\Support\ContentData;

final readonly class PageRenderer
{
    public function __construct(
        private BlockRenderer $blocks,
        private LayoutRenderer $layouts,
        private AssetCollector $assets,
        private MarkdownRenderer $markdown,
        private PartialRenderer $partials,
    ) {}

    /** @param array<string, list<array<string, mixed>>> $navigation */
    public function render(PageViewModel $page, array $navigation): RenderedPage
    {
        $context = new RenderContext(
            $page->locale(),
            $page->url(),
            $this->markdown,
            $this->partials,
        );
        $assets = $this->assets->collect($page->blocks());
        $content = '';

        foreach ($page->blocks() as $index => $block) {
            $type = ContentData::string($block['type'] ?? null, 'blocks.' . $index . '.type');
            $data = ContentData::map($block['data'] ?? null, 'blocks.' . $index . '.data');
            $content .= $this->blocks->render($type, $data, $context);
        }

        return new RenderedPage(
            $this->layouts->render($page, $content, $navigation, $assets, $context),
            max(
                $assets->modifiedAt(),
                $this->layouts->modifiedAt($page->layout()),
                $this->partials->modifiedAt(),
            ),
        );
    }
}
