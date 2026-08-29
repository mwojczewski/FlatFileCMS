<?php

declare(strict_types=1);

namespace FlatFileCms\Config;

use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;

final readonly class LanguageDocument
{
    public function __construct(
        private LanguageConfig $config,
        private FileRevision $revision,
        private int $modifiedAt,
    ) {}

    public function config(): LanguageConfig
    {
        return $this->config;
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
