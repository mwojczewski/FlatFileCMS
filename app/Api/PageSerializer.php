<?php

declare(strict_types=1);

namespace FlatFileCms\Api;

use FlatFileCms\Config\ConfigurationDocument;
use FlatFileCms\Content\PageRouteIndex;
use FlatFileCms\Domain\Content\Page;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Presentation\PageViewModelFactory;

final readonly class PageSerializer
{
    public function __construct(private PageViewModelFactory $pages) {}

    /** @return array<string, mixed> */
    public function serialize(
        Page $page,
        string $locale,
        LanguageConfig $languages,
        PageRouteIndex $routes,
        ConfigurationDocument $configuration,
    ): array {
        return $this->pages->create($page, $locale, $languages, $routes, $configuration)->toArray();
    }

    public function blockDefinitionsModifiedAt(Page $page): int
    {
        return $this->pages->blockDefinitionsModifiedAt($page);
    }
}
