<?php

declare(strict_types=1);

namespace FlatFileCms\Infrastructure\Yaml;

use FlatFileCms\Infrastructure\Filesystem\FileRevision;

final readonly class YamlDocument
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private array $data,
        private FileRevision $revision,
    ) {}

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->data;
    }

    public function revision(): FileRevision
    {
        return $this->revision;
    }
}
