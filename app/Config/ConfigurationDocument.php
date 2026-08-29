<?php

declare(strict_types=1);

namespace FlatFileCms\Config;

use FlatFileCms\Infrastructure\Filesystem\FileRevision;

final readonly class ConfigurationDocument
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private array $data,
        private FileRevision $revision,
        private int $modifiedAt,
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

    public function modifiedAt(): int
    {
        return $this->modifiedAt;
    }
}
