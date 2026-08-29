<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Blocks;

use FlatFileCms\Blocks\BlockRegistry;
use FlatFileCms\Blocks\BuiltinFieldTypes;
use FlatFileCms\Blocks\InvalidBlockDefinitionException;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlParser;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BlockRegistry::class)]
final class BlockRegistryTest extends TestCase
{
    private TemporaryProject $project;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItDiscoversAndParsesDeveloperBlockPackage(): void
    {
        $this->project->write('blocks/hero/block.yml', <<<'YAML'
schemaVersion: 1
name: { pl: Hero, en: Hero }
description: { pl: Sekcja główna, en: Main section }
icon: image
fields:
  title:
    type: text
    required: true
    translatable: true
    maxLength: 160
YAML);
        $this->project->write('blocks/hero/render.php', "<?php\n\ndeclare(strict_types=1);\n");

        $definitions = $this->registry()->all();

        self::assertSame(['hero'], array_keys($definitions));
        self::assertSame('text', $definitions['hero']->fields()['title']->type());
        self::assertTrue($definitions['hero']->fields()['title']->translatable());
    }

    public function testItRejectsBlockWithoutRenderer(): void
    {
        $this->project->write('blocks/broken/block.yml', <<<'YAML'
schemaVersion: 1
name: { pl: Broken }
fields: { }
YAML);

        $this->expectException(InvalidBlockDefinitionException::class);
        $this->registry()->all();
    }

    public function testItRejectsUnknownFieldTypeAtDiscoveryTime(): void
    {
        $this->project->write('blocks/broken/block.yml', <<<'YAML'
schemaVersion: 1
name: { pl: Broken }
fields:
  value: { type: mystery }
YAML);
        $this->project->write('blocks/broken/render.php', "<?php\n\ndeclare(strict_types=1);\n");

        $this->expectException(InvalidBlockDefinitionException::class);
        $this->registry()->all();
    }

    public function testBuiltinRegistryContainsEveryContractFieldType(): void
    {
        $types = BuiltinFieldTypes::create(new SafePathResolver($this->project->path()));

        self::assertSame(
            [
                'boolean', 'color', 'date', 'datetime', 'email', 'file', 'image', 'markdown',
                'multiselect', 'number', 'repeater', 'select', 'text', 'textarea', 'url',
            ],
            $types->names(),
        );
    }

    private function registry(): BlockRegistry
    {
        $paths = new SafePathResolver($this->project->path());

        return new BlockRegistry(
            $this->project->path(),
            new YamlParser(),
            BuiltinFieldTypes::create($paths),
        );
    }
}
