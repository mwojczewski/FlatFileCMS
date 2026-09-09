<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Media;

use FlatFileCms\Blocks\BlockProcessor;
use FlatFileCms\Blocks\BlockRegistry;
use FlatFileCms\Blocks\BlockValidator;
use FlatFileCms\Blocks\BuiltinFieldTypes;
use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Content\PageBlockManager;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\UploadedFile;
use FlatFileCms\Infrastructure\Filesystem\AtomicFileWriter;
use FlatFileCms\Infrastructure\Filesystem\FileLockManager;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlFileCache;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;
use FlatFileCms\Infrastructure\Yaml\YamlParser;
use FlatFileCms\Media\MediaException;
use FlatFileCms\Media\MediaInspector;
use FlatFileCms\Media\MediaManager;
use FlatFileCms\Media\MediaName;
use FlatFileCms\Media\MediaRepository;
use FlatFileCms\Media\MediaUrlGenerator;
use FlatFileCms\Media\MediaVariantService;
use FlatFileCms\Media\PublicMediaController;
use FlatFileCms\Media\RasterImageProcessor;
use FlatFileCms\Media\SvgSanitizer;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MediaManagerTest extends TestCase
{
    private TemporaryProject $project;
    private PageIdentity $identity;
    private MediaManager $manager;
    private MediaRepository $repository;
    private MediaVariantService $variants;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $this->identity = PageIdentity::fromString('offer');
        $this->project->write('config/setup.yml', $this->configuration());
        $this->project->write('pages/offer/content.yml', $this->content());

        $paths = new SafePathResolver($this->project->path());
        $locks = new FileLockManager($paths);
        $writer = new AtomicFileWriter($paths, $locks);
        $parser = new YamlParser();
        $yaml = new YamlFileRepository($paths, $parser, new YamlFileCache(false, $paths, $writer), $writer);
        $configuration = new ConfigurationRepository($yaml, $paths);
        $inspector = new MediaInspector(new SvgSanitizer());
        $this->repository = new MediaRepository($paths, $configuration, $inspector);
        $fields = BuiltinFieldTypes::create($paths);
        $registry = new BlockRegistry($this->project->path(), $parser, $fields);
        $validator = new BlockValidator($fields);
        $pageBlocks = new PageBlockManager(
            $yaml,
            new PageRepository($yaml, $paths),
            $registry,
            $validator,
            new BlockProcessor($registry, $validator),
            $locks,
        );
        $images = new RasterImageProcessor();
        $this->manager = new MediaManager(
            $this->repository,
            $pageBlocks,
            $configuration,
            $inspector,
            $images,
            $paths,
            $writer,
            $locks,
        );
        $this->variants = new MediaVariantService($configuration, $images, $paths, $writer);
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItUploadsNormalizesAndFingerprintsPageLocalImage(): void
    {
        $item = $this->manager->upload($this->identity, $this->imageUpload('Zażółć zdjęcie.PNG'));

        self::assertSame('zazolc-zdjecie.png', $item->name()->value());
        self::assertSame('image/png', $item->mimeType());
        self::assertSame(1, $item->width());
        self::assertFileExists($this->project->path('pages/offer/zazolc-zdjecie.png'));
        self::assertStringContainsString(
            "/media/offer/{$item->fingerprint()}/zazolc-zdjecie.png",
            (new MediaUrlGenerator())->original($this->identity, $item),
        );

        $second = $this->manager->upload($this->identity, $this->imageUpload('Zażółć zdjęcie.PNG'));
        self::assertSame('zazolc-zdjecie-2.png', $second->name()->value());
    }

    public function testItSanitizesSvgBeforeWriting(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script>'
            . '<a href="https://evil.example"><rect width="10" height="10"/></a></svg>';
        $this->project->write('storage/tmp/upload.svg', $svg);

        $item = $this->manager->upload(
            $this->identity,
            new UploadedFile($this->project->path('storage/tmp/upload.svg'), 'unsafe.svg', \strlen($svg)),
        );
        $stored = $this->repository->get($this->identity, $item->name())->contents();

        self::assertStringNotContainsString('<script', $stored);
        self::assertStringNotContainsString('onload', $stored);
        self::assertStringNotContainsString('evil.example', $stored);
    }

    public function testItRejectsUnsupportedMimeType(): void
    {
        $contents = '<?php echo "unsafe";';
        $this->project->write('storage/tmp/upload.php', $contents);

        $this->expectException(MediaException::class);
        $this->manager->upload(
            $this->identity,
            new UploadedFile($this->project->path('storage/tmp/upload.php'), 'shell.php', \strlen($contents)),
        );
    }

    public function testItBlocksDeletionWhileFileIsReferenced(): void
    {
        $item = $this->manager->upload($this->identity, $this->imageUpload('photo.png'));
        $this->project->write('pages/offer/content.yml', $this->content(
            "blocks:\n  - id: 01994d31-4fd1-7f32-9c2a-e89d624cda37\n    type: hero\n    enabled: true\n    data:\n      image:\n        src: {$item->name()->value()}\n",
        ));

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('still referenced');
        $this->manager->delete($this->identity, $item->name());
    }

    public function testItCreatesCachedWebpVariantAndServesConditionalResponse(): void
    {
        $item = $this->manager->upload($this->identity, $this->imageUpload('photo.png'));
        $file = $this->repository->get($this->identity, $item->name());
        $variant = $this->variants->create($file, 1, null, 'webp');

        self::assertSame('image/webp', $variant->mimeType());
        self::assertNotSame('', $variant->contents());
        self::assertNotEmpty(glob($this->project->path('storage/cache/media/*/*/*.webp')) ?: []);

        $controller = new PublicMediaController($this->repository, $this->variants);
        $path = "offer/{$item->fingerprint()}/{$item->name()->value()}";
        $response = $controller->show(new Request('GET', "/media/{$path}", query: ['w' => '1', 'format' => 'webp'], attributes: ['path' => $path]));
        self::assertSame(200, $response->status());
        self::assertSame('image/webp', $response->headers()['Content-Type']);

        $conditional = $controller->show(new Request(
            'GET',
            "/media/{$path}",
            headers: ['if-none-match' => $response->headers()['ETag']],
            query: ['w' => '1', 'format' => 'webp'],
            attributes: ['path' => $path],
        ));
        self::assertSame(304, $conditional->status());
        self::assertSame('', $conditional->body());

        $range = $controller->show(new Request(
            'GET',
            "/media/{$path}",
            headers: ['range' => 'bytes=0-0'],
            attributes: ['path' => $path],
        ));
        self::assertSame(206, $range->status());
        self::assertSame(1, \strlen($range->body()));
        self::assertStringStartsWith('bytes 0-0/', $range->headers()['Content-Range']);
    }

    public function testItLimitsCachedVariantsForOneSourceFile(): void
    {
        $item = $this->manager->upload($this->identity, $this->imageUpload('photo.png'));
        $file = $this->repository->get($this->identity, $item->name());
        $this->variants->create($file, 1, 1, 'webp');

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('variant limit');
        $this->variants->create($file, 2, 2, 'webp');
    }

    public function testItCreatesAnExactCoverCropForDeveloperControlledLayout(): void
    {
        $item = $this->manager->upload($this->identity, $this->sizedImageUpload('wide.png', 4, 2));
        $file = $this->repository->get($this->identity, $item->name());
        $variant = $this->variants->create($file, 2, 2, 'webp', 'cover');
        $dimensions = getimagesizefromstring($variant->contents());

        self::assertIsArray($dimensions);
        self::assertSame(2, $dimensions[0]);
        self::assertSame(2, $dimensions[1]);
    }

    private function imageUpload(string $clientFilename): UploadedFile
    {
        $contents = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        if ($contents === false) {
            throw new RuntimeException('Test image fixture is invalid.');
        }
        $path = 'storage/tmp/' . bin2hex(random_bytes(5)) . '.png';
        $this->project->write($path, $contents);

        return new UploadedFile($this->project->path($path), $clientFilename, \strlen($contents));
    }

    private function sizedImageUpload(string $clientFilename, int $width, int $height): UploadedFile
    {
        if ($width < 1 || $height < 1) {
            throw new RuntimeException('Image fixture dimensions must be positive.');
        }
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 24, 120, 220);
        if ($color === false) {
            throw new RuntimeException('Unable to allocate image fixture color.');
        }
        imagefill($image, 0, 0, $color);
        ob_start();
        try {
            imagepng($image);
            $contents = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if (!\is_string($contents) || $contents === '') {
            throw new RuntimeException('Unable to create image fixture.');
        }
        $path = 'storage/tmp/' . bin2hex(random_bytes(5)) . '.png';
        $this->project->write($path, $contents);

        return new UploadedFile($this->project->path($path), $clientFilename, \strlen($contents));
    }

    private function configuration(): string
    {
        return <<<'YAML'
schemaVersion: 1
site:
  name: Test
  url: https://example.test
  defaultLayout: default
seo: {}
media:
  stripMetadata: true
  transformations:
    enabled: true
    quality: 82
    maxWidth: 4096
    maxHeight: 4096
    maxPixels: 40000000
  cache:
    enabled: true
    maxVariantsPerMedia: 1
  formats: [webp, avif]
YAML;
    }

    private function content(string $blocks = "blocks: []\n"): string
    {
        return "schemaVersion: 1\nenabled: true\nlayout: default\nslug:\n  pl: offer\ntitle:\n  pl: Offer\nseo: {}\n{$blocks}";
    }
}
