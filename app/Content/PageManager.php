<?php

declare(strict_types=1);

namespace FlatFileCms\Content;

use FlatFileCms\Blocks\BlockProcessor;
use FlatFileCms\Collections\CollectionRepository;
use FlatFileCms\Domain\Content\Page;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Content\Slug;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Infrastructure\Filesystem\DirectoryOperator;
use FlatFileCms\Infrastructure\Filesystem\FileLockManager;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\RevisionConflictException;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;
use FlatFileCms\Rendering\LayoutRegistry;
use InvalidArgumentException;
use Throwable;

final readonly class PageManager
{
    private const string TREE_LOCK = 'pages:tree';

    public function __construct(
        private YamlFileRepository $yaml,
        private PageRepository $pages,
        private CollectionRepository $collections,
        private BlockProcessor $blocks,
        private LayoutRegistry $layouts,
        private DirectoryOperator $directories,
        private FileLockManager $locks,
        private ?ContentFileIndex $fileIndex = null,
    ) {}

    public function editable(PageIdentity $identity): EditablePage
    {
        $document = $this->yaml->read(FilesystemRoot::Pages, $this->contentPath($identity));

        return new EditablePage($identity, $document->data(), $document->revision());
    }

    public function create(
        PageIdentity $identity,
        PageMetadata $metadata,
        LanguageConfig $languages,
    ): Page {
        if ($identity->isHomepage()) {
            throw new InvalidArgumentException('The homepage is created during installation and cannot be recreated.');
        }
        $data = $this->contentData($identity, $metadata, null, $languages);
        $this->validateCandidate($identity, $data, $languages, null);

        return $this->locks->exclusive(self::TREE_LOCK, function () use ($identity, $data, $languages): Page {
            $directory = RelativePath::fromString($identity->value());
            $this->directories->create(FilesystemRoot::Pages, $directory);
            $this->fileIndex?->invalidate();
            try {
                $document = $this->yaml->write(
                    FilesystemRoot::Pages,
                    $this->contentPath($identity),
                    $data,
                    FileRevision::missing(),
                );

                return $this->pages->fromData($identity, $document->data(), $languages, $document->revision(), time());
            } catch (Throwable $exception) {
                $this->directories->delete(FilesystemRoot::Pages, $directory);
                $this->fileIndex?->invalidate();
                throw $exception;
            }
        });
    }

    public function update(
        PageIdentity $identity,
        PageMetadata $metadata,
        FileRevision $expectedRevision,
        LanguageConfig $languages,
    ): Page {
        return $this->locks->exclusive(
            self::TREE_LOCK,
            function () use ($identity, $metadata, $expectedRevision, $languages): Page {
                $editable = $this->editable($identity);
                $this->assertRevision($editable, $expectedRevision);
                $data = $this->contentData($identity, $metadata, $editable->data(), $languages);
                $this->validateCandidate($identity, $data, $languages, $identity);
                $document = $this->yaml->write(
                    FilesystemRoot::Pages,
                    $this->contentPath($identity),
                    $data,
                    $expectedRevision,
                );

                return $this->pages->fromData($identity, $document->data(), $languages, $document->revision(), time());
            },
        );
    }

    public function move(
        PageIdentity $source,
        PageIdentity $destination,
        FileRevision $expectedRevision,
        LanguageConfig $languages,
    ): void {
        if ($source->isHomepage() || $destination->isHomepage()) {
            throw new InvalidArgumentException('The homepage directory cannot be moved.');
        }
        if (str_starts_with($destination->value() . '/', $source->value() . '/')) {
            throw new InvalidArgumentException('A page cannot be moved inside its own subtree.');
        }
        $this->locks->exclusive(self::TREE_LOCK, function () use ($source, $destination, $expectedRevision, $languages): void {
            $this->assertRevision($this->editable($source), $expectedRevision);
            $this->validateTree($languages);
            $sourcePath = RelativePath::fromString($source->value());
            $destinationPath = RelativePath::fromString($destination->value());
            $this->directories->move(FilesystemRoot::Pages, $sourcePath, $destinationPath);
            $this->fileIndex?->invalidate();
            try {
                $this->validateTree($languages);
            } catch (Throwable $exception) {
                $this->directories->move(FilesystemRoot::Pages, $destinationPath, $sourcePath);
                $this->fileIndex?->invalidate();
                throw $exception;
            }
        });
    }

    public function reorganize(
        PageIdentity $source,
        ?PageIdentity $parent,
        int $position,
        FileRevision $expectedRevision,
        LanguageConfig $languages,
    ): PageIdentity {
        if ($source->isHomepage()) {
            throw new InvalidArgumentException('The homepage cannot be moved.');
        }
        $sourceValue = $source->value();
        $separator = strrpos($sourceValue, '/');
        $name = $separator === false ? $sourceValue : substr($sourceValue, $separator + 1);
        $destination = PageIdentity::fromString($parent === null ? $name : $parent->value() . '/' . $name);
        if ($parent !== null && ($parent->value() === $source->value() || str_starts_with($parent->value(), $source->value() . '/'))) {
            throw new InvalidArgumentException('A page cannot be moved inside its own subtree.');
        }

        return $this->locks->exclusive(self::TREE_LOCK, function () use ($source, $destination, $parent, $position, $expectedRevision, $languages): PageIdentity {
            $collections = [];
            foreach ($this->collections->all($languages) as $collection) {
                $collections[$collection->identity()->value()] = true;
            }
            $sourcePath = $this->treeDocumentPath($source, isset($collections[$source->value()]));
            $document = $this->yaml->read(FilesystemRoot::Pages, $sourcePath);
            if (!$document->revision()->equals($expectedRevision)) {
                throw new RevisionConflictException($expectedRevision, $document->revision());
            }

            $oldParent = $this->parentIdentity($source);
            $movedDirectory = $source->value() !== $destination->value();
            if ($movedDirectory) {
                $this->directories->move(
                    FilesystemRoot::Pages,
                    RelativePath::fromString($source->value()),
                    RelativePath::fromString($destination->value()),
                );
                $this->fileIndex?->invalidate();
            }
            try {
                // Najpierw walidacja struktury po przeniesieniu katalogu.
                // Na tym etapie żaden order nie został jeszcze zapisany.
                $this->validateTree($languages);

                $changes = $this->siblingOrderChanges(
                    $parent,
                    $destination,
                    $position,
                    $languages,
                );

                if (
                    ($oldParent?->value() ?? '')
                    !== ($parent?->value() ?? '')
                ) {
                    $oldParentChanges = $this->siblingOrderChanges(
                        $oldParent,
                        null,
                        PHP_INT_MAX,
                        $languages,
                    );

                    $changes = [...$changes, ...$oldParentChanges];
                }

                $this->applyOrderChanges($changes);
            } catch (Throwable $exception) {
                if ($movedDirectory) {
                    $this->directories->move(
                        FilesystemRoot::Pages,
                        RelativePath::fromString($destination->value()),
                        RelativePath::fromString($source->value()),
                    );

                    $this->fileIndex?->invalidate();
                }

                throw $exception;
            }

            return $destination;
        });
    }


    public function delete(PageIdentity $identity, FileRevision $expectedRevision): void
    {
        if ($identity->isHomepage()) {
            throw new InvalidArgumentException('The homepage cannot be deleted.');
        }

        $this->locks->exclusive(self::TREE_LOCK, function () use ($identity, $expectedRevision): void {
            $this->assertRevision($this->editable($identity), $expectedRevision);
            $this->directories->delete(
                FilesystemRoot::Pages,
                RelativePath::fromString($identity->value()),
            );
            $this->fileIndex?->invalidate();
        });
    }

    /**
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function contentData(
        PageIdentity $identity,
        PageMetadata $metadata,
        ?array $existing,
        LanguageConfig $languages,
    ): array {
        $this->validateMetadata($identity, $metadata, $languages);
        $data = $existing ?? [];
        $data['schemaVersion'] = 1;
        $data['enabled'] = $metadata->enabled();
        if ($metadata->layout() === null) {
            unset($data['layout']);
        } else {
            $data['layout'] = $metadata->layout();
        }
        if ($identity->isHomepage()) {
            unset($data['slug']);
        } else {
            $data['slug'] = $metadata->slugs();
        }
        $data['title'] = $metadata->titles();

        $seo = $data['seo'] ?? [];
        if (!\is_array($seo) || ($seo !== [] && array_is_list($seo))) {
            throw new InvalidContentException('Page SEO must be a mapping.');
        }
        $seo['title'] = $metadata->seoTitles();
        $seo['description'] = $metadata->seoDescriptions();
        if ($metadata->canonical() === null) {
            unset($seo['canonical']);
        } else {
            $seo['canonical'] = $metadata->canonical();
        }
        $seo['robots'] = [
            'index' => $metadata->robotsIndex(),
            'follow' => $metadata->robotsFollow(),
        ];
        $data['seo'] = $seo;
        $data['blocks'] ??= [];

        return $this->stringKeyed($data, 'Page content');
    }

    private function validateMetadata(
        PageIdentity $identity,
        PageMetadata $metadata,
        LanguageConfig $languages,
    ): void {
        $this->assertLocales($metadata->titles(), $languages, 'Page titles', true);
        $this->assertLocales($metadata->seoTitles(), $languages, 'SEO titles');
        $this->assertLocales($metadata->seoDescriptions(), $languages, 'SEO descriptions');
        if (!$identity->isHomepage()) {
            $this->assertLocales($metadata->slugs(), $languages, 'Public slugs', true);
        }
        $layout = $metadata->layout();
        if ($layout !== null) {
            $this->layouts->get($layout);
        }
        foreach ($metadata->titles() as $locale => $title) {
            if ($title === '' || mb_strlen($title) > 200) {
                throw new InvalidArgumentException(\sprintf('Title for locale "%s" must contain 1–200 characters.', $locale));
            }
        }
        if (!$identity->isHomepage()) {
            foreach ($metadata->slugs() as $slug) {
                Slug::fromString($slug);
            }
        }
        foreach ($metadata->seoTitles() as $title) {
            if (mb_strlen($title) > 200) {
                throw new InvalidArgumentException('SEO title cannot exceed 200 characters.');
            }
        }
        foreach ($metadata->seoDescriptions() as $description) {
            if (mb_strlen($description) > 500) {
                throw new InvalidArgumentException('SEO description cannot exceed 500 characters.');
            }
        }
        $canonical = $metadata->canonical();
        if ($canonical !== null && str_starts_with($canonical, '//')) {
            throw new InvalidArgumentException('Canonical site path cannot start with two slashes.');
        }
        if ($canonical !== null && !str_starts_with($canonical, '/')) {
            $scheme = parse_url($canonical, PHP_URL_SCHEME);
            if (filter_var($canonical, FILTER_VALIDATE_URL) === false || !\in_array($scheme, ['http', 'https'], true)) {
                throw new InvalidArgumentException('Canonical URL must be an HTTP(S) URL or an absolute site path.');
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function validateCandidate(
        PageIdentity $identity,
        array $data,
        LanguageConfig $languages,
        ?PageIdentity $replacedIdentity,
    ): void {
        $candidate = $this->pages->fromData(
            $identity,
            $data,
            $languages,
            FileRevision::missing(),
            time(),
        );
        foreach ($languages->codes() as $locale) {
            $this->blocks->forPublicPage($candidate, $locale, $languages);
        }
        $pages = array_values(array_filter(
            $this->pages->all($languages),
            static fn(Page $page): bool => $replacedIdentity === null
                || $page->identity()->value() !== $replacedIdentity->value(),
        ));
        $pages[] = $candidate;
        PageRouteIndex::build($pages, $languages, $this->collections->all($languages));
    }

    private function validateTree(LanguageConfig $languages): void
    {
        $pages = $this->pages->all($languages);
        foreach ($pages as $page) {
            if ($page->layout() !== null) {
                $this->layouts->get($page->layout());
            }
            foreach ($languages->codes() as $locale) {
                $this->blocks->forPublicPage($page, $locale, $languages);
            }
        }
        PageRouteIndex::build($pages, $languages, $this->collections->all($languages));
    }

    private function contentPath(PageIdentity $identity): RelativePath
    {
        return RelativePath::fromString($identity->value() . '/content.yml');
    }

    private function treeDocumentPath(PageIdentity $identity, bool $collection): RelativePath
    {
        return RelativePath::fromString($identity->value() . ($collection ? '/pagination.yml' : '/content.yml'));
    }

    private function parentIdentity(PageIdentity $identity): ?PageIdentity
    {
        $value = $identity->value();
        $separator = strrpos($value, '/');

        return $separator === false ? null : PageIdentity::fromString(substr($value, 0, $separator));
    }

    /**
     * @return array<string, array{
     *     path: RelativePath,
     *     data: array<string, mixed>
     * }>
     */
    private function siblingOrderChanges(
        ?PageIdentity $parent,
        ?PageIdentity $moved,
        int $position,
        LanguageConfig $languages,
    ): array {
        $items = [];
        $parentValue = $parent?->value() ?? '';

        foreach ($this->pages->all($languages) as $page) {
            if (
                ($this->parentIdentity($page->identity())?->value() ?? '')
                === $parentValue
                && !$page->identity()->isHomepage()
            ) {
                $items[] = [
                    'identity' => $page->identity(),
                    'collection' => false,
                    'order' => \is_int($page->attributes()['order'] ?? null)
                        ? $page->attributes()['order']
                        : 0,
                ];
            }
        }

        foreach ($this->collections->all($languages) as $collection) {
            if (
                ($this->parentIdentity($collection->identity())?->value() ?? '')
                === $parentValue
            ) {
                $items[] = [
                    'identity' => $collection->identity(),
                    'collection' => true,
                    'order' => $collection->order(),
                ];
            }
        }

        usort(
            $items,
            static fn(array $left, array $right): int =>
                [$left['order'], $left['identity']->value()]
                <=>
                [$right['order'], $right['identity']->value()],
        );

        if ($moved !== null) {
            $movedItems = array_values(array_filter(
                $items,
                static fn(array $item): bool =>
                    $item['identity']->value() === $moved->value(),
            ));

            $items = array_values(array_filter(
                $items,
                static fn(array $item): bool =>
                    $item['identity']->value() !== $moved->value(),
            ));

            if ($movedItems !== []) {
                array_splice(
                    $items,
                    max(0, min($position, \count($items))),
                    0,
                    $movedItems,
                );
            }
        }

        $changes = [];

        foreach ($items as $index => $item) {
            $path = $this->treeDocumentPath(
                $item['identity'],
                $item['collection'],
            );

            $document = $this->yaml->read(FilesystemRoot::Pages, $path);
            $data = $document->data();

            if (($data['order'] ?? null) === $index) {
                continue;
            }

            $data['order'] = $index;

            $changes[$path->value()] = [
                'path' => $path,
                'data' => $data,
            ];
        }

        return $changes;
    }

    /**
     * @param array<string, array{
     *     path: RelativePath,
     *     data: array<string, mixed>
     * }> $changes
     */
    private function applyOrderChanges(array $changes): void
    {
        /** @var array<string, array{
         *     path: RelativePath,
         *     data: array<string, mixed>
         * }> $originals
         */
        $originals = [];

        try {
            foreach ($changes as $key => $change) {
                $document = $this->yaml->read(
                    FilesystemRoot::Pages,
                    $change['path'],
                );

                $originals[$key] = [
                    'path' => $change['path'],
                    'data' => $document->data(),
                ];

                $this->yaml->write(
                    FilesystemRoot::Pages,
                    $change['path'],
                    $change['data'],
                    $document->revision(),
                );
            }
        } catch (Throwable $exception) {
            foreach (array_reverse($originals, true) as $original) {
                try {
                    $current = $this->yaml->read(
                        FilesystemRoot::Pages,
                        $original['path'],
                    );

                    $this->yaml->write(
                        FilesystemRoot::Pages,
                        $original['path'],
                        $original['data'],
                        $current->revision(),
                    );
                } catch (Throwable) {
                    // Zachowujemy pierwotny wyjątek.
                }
            }

            throw $exception;
        }
    }

    private function assertRevision(EditablePage $page, FileRevision $expected): void
    {
        if (!$page->revision()->equals($expected)) {
            throw new RevisionConflictException(
                $expected,
                $page->revision(),
            );
        }
    }

    /** @param array<string, string> $values */
    private function assertLocales(
        array $values,
        LanguageConfig $languages,
        string $field,
        bool $requireDefault = false,
    ): void {
        foreach (array_keys($values) as $locale) {
            if (!$languages->has($locale)) {
                throw new InvalidArgumentException("{$field} contains a language that is not enabled.");
            }
        }
        if ($requireDefault && !\array_key_exists($languages->default(), $values)) {
            throw new InvalidArgumentException("{$field} must contain the default language.");
        }
    }

    /**
     * @param array<mixed> $data
     * @return array<string, mixed>
     */
    private function stringKeyed(array $data, string $section): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (!\is_string($key)) {
                throw new InvalidContentException("{$section} keys must be strings.");
            }
            $result[$key] = $value;
        }

        return $result;
    }
}
