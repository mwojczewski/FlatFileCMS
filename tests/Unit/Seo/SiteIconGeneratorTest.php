<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Seo;

use FlatFileCms\Http\UploadedFile;
use FlatFileCms\Media\RasterImageProcessor;
use FlatFileCms\Seo\SiteIconGenerator;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SiteIconGenerator::class)]
final class SiteIconGeneratorTest extends TestCase
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

    public function testItGeneratesPopularIconsAndManifest(): void
    {
        $image = imagecreatetruecolor(512, 512);
        $color = imagecolorallocate($image, 22, 128, 93);
        if ($color === false) {
            self::fail('Test image color could not be allocated.');
        }
        imagefill($image, 0, 0, $color);
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        self::assertIsString($contents);
        $this->project->write('storage/tmp/icon.png', $contents);

        $paths = (new SiteIconGenerator($this->project->path(), new RasterImageProcessor()))->generate(
            new UploadedFile($this->project->path('storage/tmp/icon.png'), 'icon.png', \strlen($contents)),
            [
                'name' => 'FlatFile CMS',
                'short_name' => 'FlatFile',
                'description' => 'Panel testowy',
                'start_url' => '/',
                'scope' => '/',
                'display' => 'standalone',
                'theme_color' => '#16805d',
                'background_color' => '#ffffff',
            ],
        );

        self::assertSame('/site.webmanifest', $paths['manifest']);
        self::assertFileExists($this->project->path('public/favicon.ico'));
        self::assertFileExists($this->project->path('public/apple-touch-icon-precomposed.png'));
        self::assertFileExists($this->project->path('public/assets/icons/icon-512x512.png'));
        self::assertFileExists($this->project->path('public/site.webmanifest'));
    }
}
