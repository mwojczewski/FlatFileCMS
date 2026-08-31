<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

use FlatFileCms\Presentation\PageViewModel;
use FlatFileCms\Support\ContentData;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Media\MediaRepository;
use FlatFileCms\Media\MediaUrlGenerator;

final readonly class PageRenderer
{
    public function __construct(
        private BlockRenderer $blocks,
        private LayoutRenderer $layouts,
        private AssetCollector $assets,
        private MarkdownRenderer $markdown,
        private PartialRenderer $partials,
        private MediaRepository $media,
        private MediaUrlGenerator $mediaUrls,
    ) {}

    /** @param array<string, list<array<string, mixed>>> $navigation */
    public function render(PageViewModel $page, array $navigation): RenderedPage
    {
        $context = new RenderContext(
            $page->locale(),
            $this->markdown,
            $this->partials,
            PageIdentity::fromString($page->id()),
            $this->media,
            $this->mediaUrls,
        );
        $assets = $this->assets->collect($page->blocks());
        $content = '';

        foreach ($page->blocks() as $index => $block) {
            $type = ContentData::string($block['type'] ?? null, "blocks.{$index}.type");
            $data = ContentData::map($block['data'] ?? null, "blocks.{$index}.data");
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
