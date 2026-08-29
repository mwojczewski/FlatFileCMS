<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks\Field;

use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Localization\LanguageConfig;

final readonly class FieldContext
{
    public function __construct(
        private LanguageConfig $languages,
        private ?PageIdentity $pageIdentity = null,
    ) {}

    public function languages(): LanguageConfig
    {
        return $this->languages;
    }

    public function pageIdentity(): ?PageIdentity
    {
        return $this->pageIdentity;
    }
}
