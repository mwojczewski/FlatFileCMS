<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Admin;

use FlatFileCms\Admin\BlockFormDataMapper;
use FlatFileCms\Blocks\BlockRegistry;
use FlatFileCms\Blocks\BuiltinFieldTypes;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlParser;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BlockFormDataMapper::class)]
final class BlockFormDataMapperTest extends TestCase
{
    private TemporaryProject $project;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $this->project->write('blocks/cards/block.yml', <<<'YAML'
schemaVersion: 1
name: { pl: Karty }
fields:
  heading: { type: text, required: true, translatable: true }
  visible: { type: boolean, required: true }
  cards:
    type: repeater
    fields:
      label: { type: text, required: true, translatable: true }
YAML);
        $this->project->write('blocks/cards/render.php', "<?php\n\ndeclare(strict_types=1);\n");
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItMapsNestedPhpFormValuesWithoutKnowingTheBlockType(): void
    {
        $paths = new SafePathResolver($this->project->path());
        $registry = new BlockRegistry(
            $this->project->path(),
            new YamlParser(),
            BuiltinFieldTypes::create($paths),
        );
        $mapped = (new BlockFormDataMapper())->map(
            $registry->get('cards'),
            [
                'heading' => ['pl' => ' Nagłówek ', 'en' => ' Heading '],
                'visible' => 'false',
                'cards' => [
                    ['label' => ['pl' => ' Pierwsza ', 'en' => ' First ']],
                ],
            ],
            new LanguageConfig('pl', ['pl' => 'Polski', 'en' => 'English']),
        );

        self::assertSame(['pl' => 'Nagłówek', 'en' => 'Heading'], $mapped['heading']);
        self::assertSame('false', $mapped['visible']);
        self::assertSame(
            [['label' => ['pl' => 'Pierwsza', 'en' => 'First']]],
            $mapped['cards'],
        );
    }

    public function testItOmitsBlankNonDefaultTranslationsSoFallbackRemainsActive(): void
    {
        $paths = new SafePathResolver($this->project->path());
        $registry = new BlockRegistry(
            $this->project->path(),
            new YamlParser(),
            BuiltinFieldTypes::create($paths),
        );
        $mapped = (new BlockFormDataMapper())->map(
            $registry->get('cards'),
            [
                'heading' => ['pl' => 'Nagłówek', 'en' => ''],
                'visible' => 'true',
                'cards' => [],
            ],
            new LanguageConfig('pl', ['pl' => 'Polski', 'en' => 'English']),
        );

        self::assertSame(['pl' => 'Nagłówek'], $mapped['heading']);
    }
}
