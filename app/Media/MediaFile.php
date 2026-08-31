<?php

declare(strict_types=1);

namespace FlatFileCms\Media;

final readonly class MediaFile
{
    public function __construct(private MediaItem $item, private string $contents) {}

    public function item(): MediaItem
    {
        return $this->item;
    }

    public function contents(): string
    {
        return $this->contents;
    }
}
