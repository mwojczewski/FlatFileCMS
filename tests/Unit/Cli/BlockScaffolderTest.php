<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Cli;

use FlatFileCms\Blocks\BlockRegistry;
use FlatFileCms\Blocks\BuiltinFieldTypes;
use FlatFileCms\Cli\BlockScaffolder;
use FlatFileCms\Cli\BlockScaffolderException;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlParser;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BlockScaffolder::class)]
final class BlockScaffolderTest extends TestCase
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

    public function testItCreatesACompleteBlockPackageRecognizedByRegistry(): void
    {
        $files = (new BlockScaffolder($this->project->path()))->create('image-with-text', true);

        self::assertSame(
            [
                'blocks/image-with-text/block.yml',
                'blocks/image-with-text/render.php',
                'blocks/image-with-text/style.css',
                'blocks/image-with-text/script.js',
            ],
            $files,
        );
        foreach ($files as $file) {
            self::assertFileExists($this->project->path($file));
        }

        $paths = new SafePathResolver($this->project->path());
        $registry = new BlockRegistry(
            $this->project->path(),
            new YamlParser(),
            BuiltinFieldTypes::create($paths),
        );

        self::assertSame('image-with-text', $registry->get('image-with-text')->type());
    }

    public function testItNeverOverwritesAnExistingBlock(): void
    {
        $this->project->write('blocks/hero/block.yml', 'original');
        $scaffolder = new BlockScaffolder($this->project->path());

        try {
            $scaffolder->create('hero');
            self::fail('Expected existing block collision.');
        } catch (BlockScaffolderException) {
            self::assertSame('original', file_get_contents($this->project->path('blocks/hero/block.yml')));
        }
    }

    public function testItRejectsUnsafeBlockType(): void
    {
        $this->expectException(BlockScaffolderException::class);
        (new BlockScaffolder($this->project->path()))->create('../hero');
    }
}
