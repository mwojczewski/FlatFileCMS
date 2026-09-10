<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Content;

use FlatFileCms\Blocks\BlockProcessor;
use FlatFileCms\Blocks\BlockRegistry;
use FlatFileCms\Blocks\BlockValidator;
use FlatFileCms\Blocks\BuiltinFieldTypes;
use FlatFileCms\Collections\CollectionRepository;
use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Content\PageManager;
use FlatFileCms\Content\PageMetadata;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Infrastructure\Filesystem\AtomicFileWriter;
use FlatFileCms\Infrastructure\Filesystem\DirectoryOperator;
use FlatFileCms\Infrastructure\Filesystem\FileLockManager;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\RevisionConflictException;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlFileCache;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;
use FlatFileCms\Infrastructure\Yaml\YamlParser;
use FlatFileCms\Rendering\LayoutRegistry;
use FlatFileCms\Tests\Support\TemporaryProject;
use FlatFileCms\Tests\Support\TestContentFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageManager::class)]
final class PageManagerTest extends TestCase
{
    private TemporaryProject $project;
    private LanguageConfig $languages;
    private PageManager $manager;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $this->languages = new LanguageConfig('pl', ['pl' => 'Polski']);
        $this->project->write('pages/homepage/content.yml', <<<'YAML'
schemaVersion: 1
enabled: true
title:
  pl: Strona główna
seo: { }
blocks: []
YAML);
        $this->project->write(
            'templates/layouts/collection.php',
            '<?php declare(strict_types=1);',
        );
        $this->manager = $this->manager();
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItCreatesUpdatesMovesAndDeletesAPageDirectory(): void
    {
        $identity = PageIdentity::fromString('offer');
        $created = $this->manager->create($identity, $this->metadata('Oferta', 'oferta'), $this->languages);
        self::assertFileExists($this->project->path('pages/offer/content.yml'));
        self::assertSame('Oferta', $created->title('pl', 'pl'));

        $revision = $this->manager->editable($identity)->revision();
        $updated = $this->manager->update(
            $identity,
            $this->metadata('Usługi', 'uslugi', false),
            $revision,
            $this->languages,
        );
        self::assertFalse($updated->enabled());
        self::assertSame('Usługi', $updated->title('pl', 'pl'));

        $this->manager->move(
            $identity,
            PageIdentity::fromString('services'),
            $updated->revision(),
            $this->languages,
        );
        self::assertDirectoryDoesNotExist($this->project->path('pages/offer'));
        self::assertFileExists($this->project->path('pages/services/content.yml'));

        $moved = $this->manager->editable(PageIdentity::fromString('services'));
        $this->manager->delete($moved->identity(), $moved->revision());
        self::assertDirectoryDoesNotExist($this->project->path('pages/services'));
    }

    public function testItRejectsLocalizedRouteCollisionsBeforeCreatingDirectory(): void
    {
        $this->manager->create(
            PageIdentity::fromString('first'),
            $this->metadata('Pierwsza', 'wspolny-slug'),
            $this->languages,
        );

        $this->expectException(InvalidContentException::class);
        try {
            $this->manager->create(
                PageIdentity::fromString('second'),
                $this->metadata('Druga', 'wspolny-slug'),
                $this->languages,
            );
        } finally {
            self::assertDirectoryDoesNotExist($this->project->path('pages/second'));
        }
    }

    public function testItPreventsAStaleEditorFromOverwritingPageContent(): void
    {
        $identity = PageIdentity::fromString('offer');
        $page = $this->manager->create($identity, $this->metadata('Oferta', 'oferta'), $this->languages);
        $this->manager->update(
            $identity,
            $this->metadata('Nowa oferta', 'oferta'),
            $page->revision(),
            $this->languages,
        );

        $this->expectException(RevisionConflictException::class);
        $this->manager->update(
            $identity,
            $this->metadata('Nieaktualna oferta', 'oferta'),
            $page->revision(),
            $this->languages,
        );
    }

    public function testDeletingAParentRemovesChildrenAndMedia(): void
    {
        $parent = PageIdentity::fromString('offer');
        $this->manager->create($parent, $this->metadata('Oferta', 'oferta'), $this->languages);
        $this->manager->create(
            PageIdentity::fromString('offer/websites'),
            $this->metadata('Strony WWW', 'strony-www'),
            $this->languages,
        );
        $this->project->write('pages/offer/hero.jpg', 'image');

        $editable = $this->manager->editable($parent);
        $this->manager->delete($parent, $editable->revision());

        self::assertDirectoryDoesNotExist($this->project->path('pages/offer'));
    }

    public function testItReordersPagesWithinTheSameParent(): void
    {
        $firstIdentity = PageIdentity::fromString('first');
        $secondIdentity = PageIdentity::fromString('second');

        $this->manager->create(
            $firstIdentity,
            $this->metadata('Pierwsza', 'pierwsza'),
            $this->languages,
        );

        $second = $this->manager->create(
            $secondIdentity,
            $this->metadata('Druga', 'druga'),
            $this->languages,
        );

        $this->manager->reorganize(
            $secondIdentity,
            null,
            0,
            $second->revision(),
            $this->languages,
        );

        self::assertSame(
            0,
            $this->documentOrder('second/content.yml'),
        );

        self::assertSame(
            1,
            $this->documentOrder('first/content.yml'),
        );
    }

    public function testItMovesACollectionUnderAPage(): void
    {
        $parent = PageIdentity::fromString('documentation');

        $this->manager->create(
            $parent,
            $this->metadata('Dokumentacja', 'dokumentacja'),
            $this->languages,
        );

        $this->writeCollection('articles', 'Artykuły', 0);

        $yaml = TestContentFactory::yaml($this->project);
        $collection = $yaml->read(
            FilesystemRoot::Pages,
            RelativePath::fromString('articles/pagination.yml'),
        );

        $destination = $this->manager->reorganize(
            PageIdentity::fromString('articles'),
            $parent,
            0,
            $collection->revision(),
            $this->languages,
        );

        self::assertSame(
            'documentation/articles',
            $destination->value(),
        );

        self::assertFileDoesNotExist(
            $this->project->path('pages/articles/pagination.yml'),
        );

        self::assertFileExists(
            $this->project->path(
                'pages/documentation/articles/pagination.yml',
            ),
        );

        self::assertSame(
            0,
            $this->documentOrder(
                'documentation/articles/pagination.yml',
            ),
        );
    }

    public function testItRejectsMovingAPageIntoItsOwnDescendant(): void
    {
        $parent = PageIdentity::fromString('documentation');

        $created = $this->manager->create(
            $parent,
            $this->metadata('Dokumentacja', 'dokumentacja'),
            $this->languages,
        );

        $this->manager->create(
            PageIdentity::fromString('documentation/api'),
            $this->metadata('API', 'api'),
            $this->languages,
        );

        $this->expectException(InvalidArgumentException::class);

        $this->manager->reorganize(
            $parent,
            PageIdentity::fromString('documentation/api'),
            0,
            $created->revision(),
            $this->languages,
        );
    }

    private function manager(): PageManager
    {
        $paths = new SafePathResolver($this->project->path());
        $locks = new FileLockManager($paths);
        $writer = new AtomicFileWriter($paths, $locks);
        $parser = new YamlParser();
        $yaml = new YamlFileRepository($paths, $parser, new YamlFileCache(false, $paths, $writer), $writer);
        $pages = new PageRepository($yaml, $paths);
        $fieldTypes = BuiltinFieldTypes::create($paths);

        return new PageManager(
            $yaml,
            $pages,
            new CollectionRepository($yaml, $paths),
            new BlockProcessor(
                new BlockRegistry($this->project->path(), $parser, $fieldTypes),
                new BlockValidator($fieldTypes),
            ),
            new LayoutRegistry($this->project->path()),
            new DirectoryOperator($paths),
            $locks,
        );
    }

    private function metadata(string $title, string $slug, bool $enabled = true): PageMetadata
    {
        return new PageMetadata(
            $enabled,
            null,
            ['pl' => $title],
            ['pl' => $slug],
            ['pl' => ''],
            ['pl' => ''],
            null,
            true,
            true,
        );
    }

    private function writeCollection(
        string $identity,
        string $title,
        int $order,
    ): void {
        $this->project->write(
            "pages/{$identity}/pagination.yml",
            <<<YAML
schemaVersion: 1
type: collection
source: children
enabled: true
layout: collection
slug: { pl: {$identity} }
title: { pl: {$title} }
seo: {}
sort: { field: date, direction: desc }
pagination: { perPage: 10 }
filters: []
order: {$order}
YAML,
        );
    }

    private function documentOrder(
        string $path,
    ): int {
        $document = TestContentFactory::yaml($this->project)->read(
            FilesystemRoot::Pages,
            RelativePath::fromString($path),
        );

        $order = $document->data()['order'] ?? null;
        self::assertIsInt($order);

        return $order;
    }
}
