<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

use FlatFileCms\Presentation\PageViewModel;

final readonly class LayoutRenderer
{
    public function __construct(
        private LayoutRegistry $registry,
        private OutputBuffer $buffer,
    ) {}

    /** @param array<string, list<array<string, mixed>>> $navigation */
    public function render(
        PageViewModel $page,
        string $content,
        array $navigation,
        AssetCollection $assets,
        RenderContext $context,
    ): string {
        $template = $this->registry->get($page->layout());
        $seo = $page->seo();

        return $this->buffer->capture(
            static function () use ($template, $page, $seo, $content, $navigation, $assets, $context): void {
                require $template;
            },
        );
    }

    public function modifiedAt(string $layout): int
    {
        return $this->registry->modifiedAt($layout);
    }
}
