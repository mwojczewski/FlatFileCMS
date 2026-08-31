<?php

declare(strict_types=1);

namespace FlatFileCms\Navigation;

use FlatFileCms\Collections\CollectionRepository;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Content\PageRouteIndex;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Yaml\YamlDocument;

final readonly class NavigationManager
{
    public function __construct(
        private NavigationRepository $navigation,
        private LanguageRepository $languages,
        private PageRepository $pages,
        private CollectionRepository $collections,
    ) {}

    public function editable(): YamlDocument
    {
        return $this->navigation->raw();
    }

    /** @param array<string, mixed> $data */
    public function update(array $data, FileRevision $revision): NavigationDocument
    {
        $languages = $this->languages->get();
        $pages = $this->pages->all($languages);
        $collections = $this->collections->all($languages);
        $routes = PageRouteIndex::build($pages, $languages, $collections);

        return $this->navigation->update($data, $revision, $languages, $routes);
    }
}
