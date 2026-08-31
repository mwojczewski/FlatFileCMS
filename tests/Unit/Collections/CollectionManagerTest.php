<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Collections;

use FlatFileCms\Collections\CollectionManager;
use FlatFileCms\Collections\CollectionRepository;
use FlatFileCms\Collections\CollectionSettings;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Infrastructure\Filesystem\FileLockManager;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Rendering\LayoutRegistry;
use FlatFileCms\Tests\Support\TemporaryProject;
use FlatFileCms\Tests\Support\TestContentFactory;
use PHPUnit\Framework\TestCase;

final class CollectionManagerTest extends TestCase
{
    private TemporaryProject $project;
    private CollectionManager $manager;
    private LanguageConfig $languages;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $this->project->write('templates/layouts/collection.php', '<?php declare(strict_types=1);');
        $this->project->write('pages/homepage/content.yml', <<<'YAML'
schemaVersion: 1
enabled: true
title: { pl: Start, en: Home }
blocks: []
YAML);
        $this->project->write('pages/blog/pagination.yml', <<<'YAML'
schemaVersion: 1
type: collection
source: children
enabled: true
layout: collection
slug: { pl: blog, en: journal }
title: { pl: Blog, en: Journal }
seo: { title: { pl: Blog, en: Journal }, description: { pl: '', en: '' } }
sort: { field: date, direction: desc }
pagination: { perPage: 12 }
filters: []
YAML);
        $yaml = TestContentFactory::yaml($this->project);
        $paths = new SafePathResolver($this->project->path());
        $collections = new CollectionRepository($yaml, $paths);
        $this->manager = new CollectionManager(
            $yaml,
            $collections,
            new PageRepository($yaml, $paths),
            new LayoutRegistry($this->project->path()),
            new FileLockManager($paths),
        );
        $this->languages = new LanguageConfig('pl', ['pl' => 'Polski', 'en' => 'English']);
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItAtomicallyUpdatesCollectionQueryAndLocalizedMetadata(): void
    {
        $identity = PageIdentity::fromString('blog');
        $editable = $this->manager->editable($identity);
        $updated = $this->manager->update($identity, new CollectionSettings(
            true,
            'collection',
            ['pl' => 'aktualnosci', 'en' => 'news'],
            ['pl' => 'Aktualności', 'en' => 'News'],
            ['pl' => 'Aktualności', 'en' => 'News'],
            ['pl' => 'Najnowsze wpisy', 'en' => 'Latest posts'],
            'https://example.com/pl/aktualnosci',
            true,
            true,
            'date',
            'asc',
            24,
            [['parameter' => 'category', 'field' => 'category', 'allowedValues' => ['news']]],
        ), $editable->revision(), $this->languages);

        self::assertSame('aktualnosci', $updated->slug('pl')?->value());
        self::assertSame('asc', $updated->sortDirection());
        self::assertSame(24, $updated->perPage());
        self::assertSame('category', $updated->filters()[0]->parameter());
    }
}
