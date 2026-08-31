<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Blocks;

use FlatFileCms\Blocks\BlockRegistry;
use FlatFileCms\Blocks\BlockValidationException;
use FlatFileCms\Blocks\BlockValidator;
use FlatFileCms\Blocks\BuiltinFieldTypes;
use FlatFileCms\Blocks\ValidationError;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlParser;
use FlatFileCms\Support\ContentData;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BlockValidator::class)]
#[CoversClass(BlockValidationException::class)]
final class BlockValidatorTest extends TestCase
{
    private TemporaryProject $project;
    private BlockRegistry $registry;
    private BlockValidator $validator;
    private LanguageConfig $languages;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $paths = new SafePathResolver($this->project->path());
        $fieldTypes = BuiltinFieldTypes::create($paths);
        $this->registry = new BlockRegistry($this->project->path(), new YamlParser(), $fieldTypes);
        $this->validator = new BlockValidator($fieldTypes);
        $this->languages = new LanguageConfig('pl', ['pl' => 'Polski', 'en' => 'English']);
        $this->writeDefinition();
        $this->project->write('pages/offer/photo.jpg', 'test-image');
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItNormalizesAndLocalizesSchemaAwareData(): void
    {
        $definition = $this->registry->get('complex');
        $normalized = $this->validator->validate(
            $definition,
            [
                'title' => ['pl' => ' Oferta ', 'en' => ' Services '],
                'count' => '4',
                'active' => 'true',
                'tone' => 'dark',
                'tags' => ['new', 'featured', 'new'],
                'website' => 'https://example.com/offer',
                'date' => '2026-08-29',
                'color' => '#AABBCC',
                'image' => [
                    'src' => 'photo.jpg',
                    'alt' => ['pl' => 'Zdjęcie', 'en' => 'Photo'],
                ],
                'items' => [
                    ['label' => ['pl' => 'Pierwszy', 'en' => 'First']],
                ],
            ],
            $this->languages,
            PageIdentity::fromString('offer'),
        );
        $english = $this->validator->localize(
            $definition,
            $normalized,
            'en',
            $this->languages,
            PageIdentity::fromString('offer'),
        );
        $image = ContentData::map($english['image'] ?? null, 'image');
        $items = ContentData::list($english['items'] ?? null, 'items');
        $firstItem = ContentData::map($items[0] ?? null, 'items.0');

        self::assertSame('Services', $english['title']);
        self::assertSame(4, $english['count']);
        self::assertTrue($english['active']);
        self::assertSame(['new', 'featured'], $english['tags']);
        self::assertSame('#aabbcc', $english['color']);
        self::assertSame('Photo', $image['alt']);
        self::assertSame('First', $firstItem['label']);
    }

    public function testItReportsMultipleValidationErrorsWithPaths(): void
    {
        try {
            $this->validator->validate(
                $this->registry->get('complex'),
                [
                    'title' => ['pl' => 'Oferta'],
                    'count' => -1,
                    'active' => true,
                    'tone' => 'unknown',
                    'tags' => [],
                    'website' => 'javascript:alert(1)',
                    'date' => '2026-99-99',
                    'color' => 'red',
                    'image' => ['src' => 'missing.jpg'],
                    'items' => [],
                    'unexpected' => 'value',
                ],
                $this->languages,
                PageIdentity::fromString('offer'),
            );
            self::fail('Expected schema validation to fail.');
        } catch (BlockValidationException $exception) {
            $paths = array_map(
                static fn(ValidationError $error): string => $error->path(),
                $exception->errors(),
            );

            self::assertContains('data.unexpected', $paths);
            self::assertNotContains('data.title.en', $paths);
            self::assertContains('data.count', $paths);
            self::assertContains('data.image', $paths);
        }
    }

    public function testRequiredTranslatedFieldsUseTheDefaultLanguageAsFallback(): void
    {
        $definition = $this->registry->get('complex');
        $normalized = $this->validator->validate(
            $definition,
            [
                'title' => ['pl' => 'Oferta'],
                'count' => 4,
                'active' => true,
                'tone' => 'dark',
                'tags' => ['new'],
                'website' => 'https://example.com/offer',
                'date' => '2026-08-29',
                'color' => '#aabbcc',
                'image' => ['src' => 'photo.jpg'],
                'items' => [['label' => ['pl' => 'Pierwsza']]],
            ],
            $this->languages,
            PageIdentity::fromString('offer'),
        );
        $english = $this->validator->localize(
            $definition,
            $normalized,
            'en',
            $this->languages,
            PageIdentity::fromString('offer'),
        );
        $items = ContentData::list($english['items'] ?? null, 'items');
        $firstItem = ContentData::map($items[0] ?? null, 'items.0');

        self::assertSame('Oferta', $english['title']);
        self::assertSame('Pierwsza', $firstItem['label']);
    }

    private function writeDefinition(): void
    {
        $this->project->write('blocks/complex/block.yml', <<<'YAML'
schemaVersion: 1
name: { pl: Złożony, en: Complex }
fields:
  title:
    type: text
    required: true
    translatable: true
    minLength: 1
    maxLength: 100
  count: { type: number, required: true, min: 0, max: 10, integer: true }
  active: { type: boolean, required: true }
  tone:
    type: select
    required: true
    options: [light, dark]
  tags:
    type: multiselect
    options: [new, featured]
    maxItems: 2
  website: { type: url, required: true }
  date: { type: date, required: true }
  color: { type: color, required: true }
  image: { type: image, required: true }
  items:
    type: repeater
    required: true
    minItems: 1
    fields:
      label: { type: text, required: true, translatable: true }
YAML);
        $this->project->write('blocks/complex/render.php', "<?php\n\ndeclare(strict_types=1);\n");
    }
}
