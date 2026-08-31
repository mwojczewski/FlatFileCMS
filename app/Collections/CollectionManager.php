<?php

declare(strict_types=1);

namespace FlatFileCms\Collections;

use FlatFileCms\Content\PageRepository;
use FlatFileCms\Content\PageRouteIndex;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Content\Slug;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Infrastructure\Filesystem\FileLockManager;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\RevisionConflictException;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;
use FlatFileCms\Rendering\LayoutRegistry;
use InvalidArgumentException;

final readonly class CollectionManager
{
    public function __construct(
        private YamlFileRepository $yaml,
        private CollectionRepository $collections,
        private PageRepository $pages,
        private LayoutRegistry $layouts,
        private FileLockManager $locks,
    ) {}

    public function editable(PageIdentity $identity): EditableCollection
    {
        $document = $this->yaml->read(FilesystemRoot::Pages, $this->path($identity));

        return new EditableCollection($identity, $document->data(), $document->revision());
    }

    public function update(
        PageIdentity $identity,
        CollectionSettings $settings,
        FileRevision $expectedRevision,
        LanguageConfig $languages,
    ): Collection {
        $this->validateSettings($settings, $languages);

        return $this->locks->exclusive('pages:tree', function () use ($identity, $settings, $expectedRevision, $languages): Collection {
            $editable = $this->editable($identity);
            if (!$editable->revision()->equals($expectedRevision)) {
                throw new RevisionConflictException($expectedRevision, $editable->revision());
            }

            $data = $editable->data();
            $data['schemaVersion'] = 1;
            $data['type'] = 'collection';
            $data['source'] = 'children';
            $data['enabled'] = $settings->enabled;
            $data['layout'] = $settings->layout;
            $data['slug'] = $settings->slugs;
            $data['title'] = $settings->titles;
            $seo = isset($data['seo']) && is_array($data['seo']) && !array_is_list($data['seo']) ? $data['seo'] : [];
            $seo['title'] = $settings->seoTitles;
            $seo['description'] = $settings->seoDescriptions;
            $seo['robots'] = ['index' => $settings->robotsIndex, 'follow' => $settings->robotsFollow];
            if ($settings->canonical === null) {
                unset($seo['canonical']);
            } else {
                $seo['canonical'] = $settings->canonical;
            }
            $data['seo'] = $seo;
            $data['sort'] = ['field' => $settings->sortField, 'direction' => $settings->sortDirection];
            $data['pagination'] = ['perPage' => $settings->perPage];
            $data['filters'] = $settings->filters;

            $this->layouts->get($settings->layout);
            $candidate = $this->collections->fromData($identity, $data, $languages, $expectedRevision, time());
            $all = array_values(array_filter(
                $this->collections->all($languages),
                static fn(Collection $collection): bool => $collection->identity()->value() !== $identity->value(),
            ));
            $all[] = $candidate;
            PageRouteIndex::build($this->pages->all($languages), $languages, $all);

            $document = $this->yaml->write(FilesystemRoot::Pages, $this->path($identity), $data, $expectedRevision);

            return $this->collections->fromData($identity, $document->data(), $languages, $document->revision(), time());
        });
    }

    private function path(PageIdentity $identity): RelativePath
    {
        return RelativePath::fromString("{$identity->value()}/pagination.yml");
    }

    private function validateSettings(CollectionSettings $settings, LanguageConfig $languages): void
    {
        foreach ($settings->titles as $locale => $title) {
            if (!$languages->has($locale) || trim($title) === '' || mb_strlen($title) > 200) {
                throw new InvalidArgumentException("Collection title for {$locale} must contain 1–200 characters.");
            }
        }
        if (!isset($settings->titles[$languages->default()], $settings->slugs[$languages->default()])) {
            throw new InvalidArgumentException('Collection title and slug require the default language.');
        }
        foreach ($settings->slugs as $locale => $slug) {
            if (!$languages->has($locale)) {
                throw new InvalidArgumentException("Collection slug for {$locale} uses a language that is not enabled.");
            }
            Slug::fromString($slug);
        }
        foreach ($settings->seoTitles as $locale => $seoTitle) {
            if (!$languages->has($locale) || mb_strlen($seoTitle) > 200) {
                throw new InvalidArgumentException("Collection SEO title for {$locale} is invalid.");
            }
        }
        foreach ($settings->seoDescriptions as $locale => $seoDescription) {
            if (!$languages->has($locale) || mb_strlen($seoDescription) > 500) {
                throw new InvalidArgumentException("Collection SEO description for {$locale} is invalid.");
            }
        }
        $canonical = $settings->canonical;
        if ($canonical !== null && str_starts_with($canonical, '//')) {
            throw new InvalidArgumentException('Collection canonical site path cannot start with two slashes.');
        }
        if ($canonical !== null && !str_starts_with($canonical, '/')) {
            $scheme = parse_url($canonical, PHP_URL_SCHEME);
            if (filter_var($canonical, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true)) {
                throw new InvalidArgumentException('Collection canonical must be an HTTP(S) URL or absolute site path.');
            }
        }
    }
}
