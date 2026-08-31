<?php

declare(strict_types=1);

namespace FlatFileCms\Media;

final readonly class MediaItem
{
    public function __construct(
        private MediaName $name,
        private string $mimeType,
        private int $size,
        private int $modifiedAt,
        private string $hash,
        private ?int $width = null,
        private ?int $height = null,
    ) {}

    public function name(): MediaName
    {
        return $this->name;
    }
    public function mimeType(): string
    {
        return $this->mimeType;
    }
    public function size(): int
    {
        return $this->size;
    }
    public function modifiedAt(): int
    {
        return $this->modifiedAt;
    }
    public function hash(): string
    {
        return $this->hash;
    }
    public function fingerprint(): string
    {
        return substr($this->hash, 0, 16);
    }
    public function width(): ?int
    {
        return $this->width;
    }
    public function height(): ?int
    {
        return $this->height;
    }
    public function isImage(): bool
    {
        return MediaTypes::isImage($this->mimeType);
    }
}
