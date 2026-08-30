<?php

declare(strict_types=1);

namespace FlatFileCms\Api;

use FlatFileCms\Collections\CollectionResult;
use FlatFileCms\Config\ConfigurationDocument;
use FlatFileCms\Content\PageRouteIndex;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Presentation\CollectionViewModelFactory;

final readonly class CollectionSerializer
{
    public function __construct(private CollectionViewModelFactory $collections) {}

    /** @return array<string, mixed> */
    public function serialize(
        CollectionResult $result,
        string $locale,
        LanguageConfig $languages,
        PageRouteIndex $routes,
        ConfigurationDocument $configuration,
    ): array {
        return $this->collections->create($result, $locale, $languages, $routes, $configuration)->toArray();
    }
}
