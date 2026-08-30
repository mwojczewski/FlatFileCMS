<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

use FlatFileCms\Blocks\BlockRegistry;

final readonly class BlockRenderer
{
    public function __construct(
        private BlockRegistry $registry,
        private OutputBuffer $buffer,
    ) {}

    /** @param array<string, mixed> $data */
    public function render(string $type, array $data, RenderContext $context): string
    {
        $template = $this->registry->get($type)->renderer();

        return $this->buffer->capture(static function () use ($template, $data, $context): void {
            require $template;
        });
    }
}
