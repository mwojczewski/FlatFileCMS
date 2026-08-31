<?php

declare(strict_types=1);

namespace FlatFileCms\Config;

use FlatFileCms\Infrastructure\Filesystem\FileRevision;

final readonly class SiteTextDocument
{
    public function __construct(
        private string $contents,
        private FileRevision $revision,
    ) {}

    public function contents(): string
    {
        return $this->contents;
    }

    public function revision(): FileRevision
    {
        return $this->revision;
    }
}
