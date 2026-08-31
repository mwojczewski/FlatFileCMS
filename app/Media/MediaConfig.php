<?php

declare(strict_types=1);

namespace FlatFileCms\Media;

use FlatFileCms\Config\ConfigurationDocument;
use InvalidArgumentException;

final readonly class MediaConfig
{
    /**
     * @param list<string> $allowedMimeTypes
     * @param list<string> $formats
     */
    public function __construct(
        private int $maximumUploadBytes,
        private array $allowedMimeTypes,
        private bool $stripMetadata,
        private bool $transformationsEnabled,
        private bool $cacheEnabled,
        private array $formats,
        private int $quality,
        private int $maximumWidth,
        private int $maximumHeight,
        private int $maximumPixels,
    ) {}

    public static function fromDocument(ConfigurationDocument $document): self
    {
        $root = self::mapping($document->data()['media'] ?? []);
        $transformations = self::mapping($root['transformations'] ?? []);
        $cache = self::mapping($root['cache'] ?? []);

        return new self(
            self::integer($root['maxUploadBytes'] ?? 26_214_400, 'media.maxUploadBytes', 1, 104_857_600),
            self::mimeTypes($root['allowedMimeTypes'] ?? MediaTypes::defaults()),
            self::boolean($root['stripMetadata'] ?? true, 'media.stripMetadata'),
            self::boolean($transformations['enabled'] ?? true, 'media.transformations.enabled'),
            self::boolean($cache['enabled'] ?? true, 'media.cache.enabled'),
            self::formats($root['formats'] ?? ['webp', 'avif']),
            self::integer($transformations['quality'] ?? 82, 'media.transformations.quality', 1, 100),
            self::integer($transformations['maxWidth'] ?? 4096, 'media.transformations.maxWidth', 1, 8192),
            self::integer($transformations['maxHeight'] ?? 4096, 'media.transformations.maxHeight', 1, 8192),
            self::integer($transformations['maxPixels'] ?? 40_000_000, 'media.transformations.maxPixels', 1, 100_000_000),
        );
    }

    public function maximumUploadBytes(): int
    {
        return $this->maximumUploadBytes;
    }

    public function allows(string $mimeType): bool
    {
        return \in_array($mimeType, $this->allowedMimeTypes, true);
    }

    /** @return list<string> */
    public function allowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }

    public function stripMetadata(): bool
    {
        return $this->stripMetadata;
    }

    public function transformationsEnabled(): bool
    {
        return $this->transformationsEnabled;
    }

    public function cacheEnabled(): bool
    {
        return $this->cacheEnabled;
    }

    public function allowsFormat(string $format): bool
    {
        return \in_array($format, $this->formats, true);
    }

    public function quality(): int
    {
        return $this->quality;
    }

    public function maximumWidth(): int
    {
        return $this->maximumWidth;
    }

    public function maximumHeight(): int
    {
        return $this->maximumHeight;
    }

    public function maximumPixels(): int
    {
        return $this->maximumPixels;
    }

    /** @return array<string, mixed> */
    private static function mapping(mixed $value): array
    {
        if (!\is_array($value) || ($value !== [] && \array_is_list($value))) {
            throw new InvalidArgumentException('Media configuration section must be a mapping.');
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException('Media configuration contains a non-string key.');
            }
            $result[$key] = $item;
        }

        return $result;
    }

    private static function boolean(mixed $value, string $path): bool
    {
        if (!\is_bool($value)) {
            throw new InvalidArgumentException("{$path} must be a boolean.");
        }

        return $value;
    }

    private static function integer(mixed $value, string $path, int $minimum, int $maximum): int
    {
        if (!\is_int($value) || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException("{$path} is outside the allowed range.");
        }

        return $value;
    }

    /** @return list<string> */
    private static function strings(mixed $value, string $path): array
    {
        if (!\is_array($value) || !\array_is_list($value) || $value === []) {
            throw new InvalidArgumentException("{$path} must be a non-empty list.");
        }

        $result = [];
        foreach ($value as $item) {
            if (!\is_string($item) || $item === '') {
                throw new InvalidArgumentException("{$path} contains an invalid value.");
            }
            $result[] = $item;
        }

        return \array_values(\array_unique($result));
    }

    /** @return list<string> */
    private static function mimeTypes(mixed $value): array
    {
        $types = self::strings($value, 'media.allowedMimeTypes');
        foreach ($types as $type) {
            if (MediaTypes::extension($type) === null) {
                throw new InvalidArgumentException("Unsupported media MIME type: {$type}.");
            }
        }

        return $types;
    }

    /** @return list<string> */
    private static function formats(mixed $value): array
    {
        $formats = self::strings($value, 'media.formats');
        foreach ($formats as $format) {
            if (!\in_array($format, ['avif', 'webp'], true)) {
                throw new InvalidArgumentException("Unsupported generated media format: {$format}.");
            }
        }

        return $formats;
    }
}
