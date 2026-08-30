<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

final readonly class RenderedPage
{
    public function __construct(
        private string $html,
        private int $assetsModifiedAt,
    ) {}

    public function html(): string
    {
        return $this->html;
    }

    public function assetsModifiedAt(): int
    {
        return $this->assetsModifiedAt;
    }
}
