<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Http\ErrorView;
use FlatFileCms\Media\MediaRepository;
use FlatFileCms\Media\MediaUrlGenerator;
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
        private MediaRepository $media,
        private MediaUrlGenerator $mediaUrls,
        private ?string $cloudflareBeaconToken = null,
    ) {}

    /** @param array<string, list<array<string, mixed>>> $navigation */
    public function render(
        PageViewModel $page,
        array $navigation,
        ?ErrorView $error = null,
    ): RenderedPage {
        $context = new RenderContext(
            $page->locale(),
            $this->markdown,
            $this->partials,
            PageIdentity::fromString($page->id()),
            $this->media,
            $this->mediaUrls,
            $this->cloudflareBeaconToken,
        );
        $assets = $this->assets->collect($page->blocks());
        $content = '';

        foreach ($page->blocks() as $index => $block) {
            $type = ContentData::string($block['type'] ?? null, "blocks.{$index}.type");
            $data = ContentData::map($block['data'] ?? null, "blocks.{$index}.data");
            $data['_block_id'] = ContentData::string($block['id'] ?? null, "blocks.{$index}.id");
            $data['_page_id'] = $page->id();
            $data['_return_path'] = $page->url();
            $content .= $this->blocks->render($type, $data, $context, $error);
        }

        return new RenderedPage(
            $this->layouts->render($page, $content, $navigation, $assets, $context, $error),
            max(
                $assets->modifiedAt(),
                $this->layouts->modifiedAt($page->layout()),
                $this->partials->modifiedAt(),
            ),
        );
    }
}
