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
            try {
                $this->validateTree($languages);
            } catch (Throwable $exception) {
                $this->directories->move(FilesystemRoot::Pages, $destinationPath, $sourcePath);
                throw $exception;
            }
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
        $this->assertLocales($metadata->titles(), $languages, 'Page titles');
        $this->assertLocales($metadata->seoTitles(), $languages, 'SEO titles');
        $this->assertLocales($metadata->seoDescriptions(), $languages, 'SEO descriptions');
        if (!$identity->isHomepage()) {
            $this->assertLocales($metadata->slugs(), $languages, 'Public slugs');
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
    private function assertLocales(array $values, LanguageConfig $languages, string $field): void
    {
        $expected = $languages->codes();
        $actual = array_keys($values);
        sort($expected);
        sort($actual);
        if ($actual !== $expected) {
            throw new InvalidArgumentException("{$field} must contain exactly all enabled languages.");
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
