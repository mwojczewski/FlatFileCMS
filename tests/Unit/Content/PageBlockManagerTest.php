<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Content;

use FlatFileCms\Blocks\BlockProcessor;
use FlatFileCms\Blocks\BlockRegistry;
use FlatFileCms\Blocks\BlockValidationException;
use FlatFileCms\Blocks\BlockValidator;
use FlatFileCms\Blocks\BuiltinFieldTypes;
use FlatFileCms\Content\PageBlockManager;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Infrastructure\Filesystem\FileLockManager;
use FlatFileCms\Infrastructure\Filesystem\RevisionConflictException;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlParser;
use FlatFileCms\Support\ContentData;
use FlatFileCms\Tests\Support\TemporaryProject;
use FlatFileCms\Tests\Support\TestContentFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageBlockManager::class)]
final class PageBlockManagerTest extends TestCase
{
    private TemporaryProject $project;
    private PageBlockManager $manager;
    private LanguageConfig $languages;
    private PageIdentity $identity;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $this->languages = new LanguageConfig('pl', ['pl' => 'Polski', 'en' => 'English']);
        $this->identity = PageIdentity::fromString('offer');
        $this->project->write('blocks/text/block.yml', <<<'YAML'
schemaVersion: 1
name: { pl: Tekst, en: Text }
fields:
  title:
    type: text
    required: true
    translatable: true
    minLength: 1
  highlighted:
    type: boolean
    required: true
YAML);
        $this->project->write('blocks/text/render.php', "<?php\n\ndeclare(strict_types=1);\n");
        $this->project->write('pages/offer/content.yml', <<<'YAML'
schemaVersion: 1
enabled: true
slug: { pl: oferta, en: offer }
title: { pl: Oferta, en: Offer }
seo: { }
blocks: []
YAML);
        $this->manager = $this->manager();
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItPerformsEveryPageBuilderMutationWithStableIdentifiers(): void
    {
        $revision = $this->manager->editable($this->identity)->revision();
        $created = $this->manager->add(
            $this->identity,
            'text',
            ['title' => ['pl' => 'Pierwszy', 'en' => 'First'], 'highlighted' => false],
            $revision,
            $this->languages,
        );
        $first = $this->blocks($created->data())[0];
        $firstId = ContentData::string($first['id'] ?? null, 'id');
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
            $firstId,
        );

        $duplicated = $this->manager->duplicate(
            $this->identity,
            $firstId,
            $created->revision(),
            $this->languages,
        );
        $blocks = $this->blocks($duplicated->data());
        $secondId = ContentData::string($blocks[1]['id'] ?? null, 'id');
        self::assertNotSame($firstId, $secondId);

        $toggled = $this->manager->toggle(
            $this->identity,
            $secondId,
            $duplicated->revision(),
            $this->languages,
        );
        self::assertFalse($this->blocks($toggled->data())[1]['enabled']);

        $reordered = $this->manager->reorder(
            $this->identity,
            [$secondId, $firstId],
            $toggled->revision(),
            $this->languages,
        );
        self::assertSame($secondId, $this->blocks($reordered->data())[0]['id']);

        $updated = $this->manager->update(
            $this->identity,
            $firstId,
            ['title' => ['pl' => 'Zmieniony', 'en' => 'Changed'], 'highlighted' => true],
            $reordered->revision(),
            $this->languages,
        );
        $updatedBlock = $this->manager->block($this->identity, $firstId);
        $updatedData = ContentData::map($updatedBlock['data'] ?? null, 'data');
        self::assertSame(['pl' => 'Zmieniony', 'en' => 'Changed'], $updatedData['title']);

        $deleted = $this->manager->delete(
            $this->identity,
            $secondId,
            $updated->revision(),
            $this->languages,
        );
        self::assertCount(1, $this->blocks($deleted->data()));
    }

    public function testItRejectsAStaleBuilderRevision(): void
    {
        $revision = $this->manager->editable($this->identity)->revision();
        $this->manager->add(
            $this->identity,
            'text',
            ['title' => ['pl' => 'Pierwszy', 'en' => 'First'], 'highlighted' => false],
            $revision,
            $this->languages,
        );

        $this->expectException(RevisionConflictException::class);
        $this->manager->add(
            $this->identity,
            'text',
            ['title' => ['pl' => 'Drugi', 'en' => 'Second'], 'highlighted' => false],
            $revision,
            $this->languages,
        );
    }

    public function testInvalidBlockDataIsNeverWritten(): void
    {
        $before = $this->manager->editable($this->identity);

        try {
            $this->manager->add(
                $this->identity,
                'text',
                ['title' => ['pl' => 'Brak angielskiego'], 'highlighted' => false],
                $before->revision(),
                $this->languages,
            );
            self::fail('Invalid block data should be rejected.');
        } catch (BlockValidationException) {
            $after = $this->manager->editable($this->identity);
            self::assertTrue($after->revision()->equals($before->revision()));
            self::assertSame([], $this->blocks($after->data()));
        }
    }

    private function manager(): PageBlockManager
    {
        $paths = new SafePathResolver($this->project->path());
        $yaml = TestContentFactory::yaml($this->project);
        $fieldTypes = BuiltinFieldTypes::create($paths);
        $registry = new BlockRegistry($this->project->path(), new YamlParser(), $fieldTypes);
        $validator = new BlockValidator($fieldTypes);

        return new PageBlockManager(
            $yaml,
            new PageRepository($yaml, $paths),
            $registry,
            $validator,
            new BlockProcessor($registry, $validator),
            new FileLockManager($paths),
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    private function blocks(array $data): array
    {
        $blocks = [];
        foreach (ContentData::list($data['blocks'] ?? [], 'blocks') as $index => $block) {
            $blocks[] = ContentData::map($block, 'blocks.' . $index);
        }

        return $blocks;
    }
}
