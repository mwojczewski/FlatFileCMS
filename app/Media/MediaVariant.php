<?php

declare(strict_types=1);

namespace FlatFileCms\Media;

final readonly class MediaVariant
{
    public function __construct(
        private string $contents,
        private string $mimeType,
        private string $etag,
        private string $filename,
    ) {}

    public function contents(): string
    {
        return $this->contents;
    }
    public function mimeType(): string
    {
        return $this->mimeType;
    }
    public function etag(): string
    {
        return $this->etag;
    }
    public function filename(): string
    {
        return $this->filename;
    }
}
