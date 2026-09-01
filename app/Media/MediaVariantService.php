<?php

declare(strict_types=1);

namespace FlatFileCms\Media;

use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Infrastructure\Filesystem\AtomicFileWriter;
use FlatFileCms\Infrastructure\Filesystem\FileLockManager;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;

final readonly class MediaVariantService
{
    public function __construct(
        private ConfigurationRepository $configuration,
        private RasterImageProcessor $images,
        private SafePathResolver $paths,
        private AtomicFileWriter $writer,
        private ?FileLockManager $locks = null,
    ) {}

    public function create(
        MediaFile $file,
        ?int $width,
        ?int $height,
        ?string $format,
        string $fit = 'contain',
    ): MediaVariant {
        $item = $file->item();
        $config = MediaConfig::fromDocument($this->configuration->get());
        if (!$config->transformationsEnabled() || ($width === null && $height === null && $format === null)) {
            return $this->original($file);
        }
        $width = $this->dimension($width, $config->maximumWidth(), 'width');
        $height = $this->dimension($height, $config->maximumHeight(), 'height');
        if (!\in_array($fit, ['contain', 'cover'], true)) {
            throw new MediaException('Requested image fit mode is invalid.');
        }
        if ($fit === 'cover' && ($width === null || $height === null)) {
            throw new MediaException('Cover image variants require both width and height.');
        }
        $outputFormat = $format ?? MediaTypes::extension($item->mimeType());
        if ($outputFormat === null || !$this->formatAllowed($outputFormat, $item->mimeType(), $config)) {
            throw new MediaException('Requested media output format is not enabled.');
        }
        $key = hash('sha256', implode(':', [
            $item->hash(),
            (string) ($width ?? 0),
            (string) ($height ?? 0),
            $outputFormat,
            $fit,
            (string) $config->quality(),
        ]));
        $sourceHash = $item->hash();
        $cacheDirectory = 'cache/media/' . substr($sourceHash, 0, 2) . "/{$sourceHash}";
        $cachePath = RelativePath::fromString("{$cacheDirectory}/{$key}.{$outputFormat}");
        if ($config->cacheEnabled()) {
            $cached = $this->paths->resolve(FilesystemRoot::Storage, $cachePath);
            if (is_file($cached) && !is_link($cached)) {
                $contents = file_get_contents($cached);
                if ($contents !== false) {
                    return $this->variant($contents, $outputFormat, $key, $item);
                }
            }
        }

        $generate = function () use ($file, $item, $width, $height, $outputFormat, $config, $fit, $cachePath, $cacheDirectory, $key): MediaVariant {
            if ($config->cacheEnabled()) {
                $cached = $this->paths->resolve(FilesystemRoot::Storage, $cachePath);
                if (is_file($cached) && !is_link($cached)) {
                    $contents = file_get_contents($cached);
                    if ($contents !== false) {
                        return $this->variant($contents, $outputFormat, $key, $item);
                    }
                }
                $directory = $this->paths->resolve(FilesystemRoot::Storage, RelativePath::fromString($cacheDirectory));
                $files = is_dir($directory) ? glob($directory . '/*') : [];
                if ($files === false || \count($files) >= $config->maximumCachedVariants()) {
                    throw new MediaException('Media variant limit has been reached.');
                }
            }

            $transformed = $this->images->transform(
                $file->contents(),
                $item->mimeType(),
                $width,
                $height,
                $outputFormat,
                $config->quality(),
                $config->maximumPixels(),
                $fit,
            );
            if ($config->cacheEnabled()) {
                $this->writer->write(FilesystemRoot::Storage, $cachePath, $transformed['contents']);
            }

            return new MediaVariant(
                $transformed['contents'],
                $transformed['mimeType'],
                $key,
                $this->variantFilename($item, $key, $transformed['extension']),
            );
        };

        return $this->locks === null ? $generate() : $this->locks->exclusive('media-variants:' . $sourceHash, $generate);
    }

    private function original(MediaFile $file): MediaVariant
    {
        $item = $file->item();

        return new MediaVariant($file->contents(), $item->mimeType(), $item->hash(), $item->name()->value());
    }

    private function variant(string $contents, string $format, string $key, MediaItem $source): MediaVariant
    {
        $mimeType = match ($format) {
            'avif' => 'image/avif',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => throw new MediaException('Cached image format is invalid.'),
        };

        return new MediaVariant($contents, $mimeType, $key, $this->variantFilename($source, $key, $format));
    }

    private function dimension(?int $value, int $maximum, string $name): ?int
    {
        if ($value !== null && ($value < 1 || $value > $maximum)) {
            throw new MediaException("Requested image {$name} is outside the configured limit.");
        }

        return $value;
    }

    private function formatAllowed(string $format, string $sourceMimeType, MediaConfig $config): bool
    {
        $sourceFormat = MediaTypes::extension($sourceMimeType);

        return $format === $sourceFormat || ($format === 'jpeg' && $sourceFormat === 'jpg') || $config->allowsFormat($format);
    }

    private function variantFilename(MediaItem $source, string $hash, string $extension): string
    {
        $stem = pathinfo($source->name()->value(), PATHINFO_FILENAME);

        return "{$stem}." . substr($hash, 0, 16) . ".{$extension}";
    }
}
