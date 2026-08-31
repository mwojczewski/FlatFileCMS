<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Navigation;

use FlatFileCms\Collections\CollectionRepository;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Domain\Localization\LocalizedDataResolver;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Navigation\NavigationManager;
use FlatFileCms\Navigation\NavigationRepository;
use FlatFileCms\Support\ContentData;
use FlatFileCms\Tests\Support\TemporaryProject;
use FlatFileCms\Tests\Support\TestContentFactory;
use PHPUnit\Framework\TestCase;

final class NavigationManagerTest extends TestCase
{
    private TemporaryProject $project;
    private NavigationManager $manager;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $this->project->write('config/languages.yml', <<<'YAML'
default: pl
languages:
  en: { name: English, enabled: true }
  pl: { name: Polski, enabled: true }
YAML);
        $this->project->write('config/navigation.yml', <<<'YAML'
main:
  - label: { pl: Start, en: Home }
    link: { type: page, page: homepage }
YAML);
        $this->project->write('pages/homepage/content.yml', <<<'YAML'
schemaVersion: 1
enabled: true
title: { pl: Start, en: Home }
blocks: []
YAML);
        $this->project->write('pages/services/content.yml', <<<'YAML'
schemaVersion: 1
enabled: true
slug: { pl: oferta, en: services }
title: { pl: Oferta, en: Services }
blocks: []
YAML);
        $yaml = TestContentFactory::yaml($this->project);
        $paths = new SafePathResolver($this->project->path());
        $languages = new LanguageRepository($yaml, $paths);
        $pages = new PageRepository($yaml, $paths);
        $collections = new CollectionRepository($yaml, $paths);
        $repository = new NavigationRepository($yaml, $paths, new LocalizedDataResolver());
        $this->manager = new NavigationManager($repository, $languages, $pages, $collections);
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItAtomicallyUpdatesNestedLocalizedNavigation(): void
    {
        $revision = $this->manager->editable()->revision();
        $document = $this->manager->update([
            'main' => [[
                'label' => ['pl' => 'Oferta', 'en' => 'Services'],
                'link' => ['type' => 'page', 'page' => 'services'],
                'target' => '_self',
                'children' => [[
                    'label' => ['pl' => 'Kontakt', 'en' => 'Contact'],
                    'link' => ['type' => 'url', 'url' => '/kontakt'],
                    'target' => '_self',
                    'children' => [],
                ]],
            ]],
        ], $revision);

        $main = $document->menus()['main'];
        $first = ContentData::map($main[0] ?? null, 'main.0');
        $children = ContentData::list($first['children'] ?? null, 'main.0.children');
        $child = ContentData::map($children[0] ?? null, 'main.0.children.0');

        self::assertSame('/pl/oferta', $first['url']);
        self::assertSame('/kontakt', $child['url']);
    }

    public function testItDoesNotWriteNavigationWithMissingPageReference(): void
    {
        $editable = $this->manager->editable();
        $before = file_get_contents($this->project->path('config/navigation.yml'));

        try {
            $this->manager->update([
                'main' => [[
                    'label' => ['pl' => 'Brak', 'en' => 'Missing'],
                    'link' => ['type' => 'page', 'page' => 'missing'],
                ]],
            ], $editable->revision());
            self::fail('Expected invalid navigation to be rejected.');
        } catch (InvalidContentException) {
            self::assertSame($before, file_get_contents($this->project->path('config/navigation.yml')));
        }
    }
}
