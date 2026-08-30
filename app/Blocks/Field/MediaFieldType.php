<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks\Field;

use FlatFileCms\Blocks\FieldDefinition;
use FlatFileCms\Blocks\FieldValueException;
use FlatFileCms\Infrastructure\Filesystem\FilesystemException;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use InvalidArgumentException;

final readonly class MediaFieldType implements FieldType
{
    private const array IMAGE_EXTENSIONS = ['avif', 'gif', 'jpeg', 'jpg', 'png', 'svg', 'webp'];

    public function __construct(
        private string $name,
        private SafePathResolver $paths,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function validateDefinition(FieldDefinition $definition): void {}

    /** @return array<string, mixed> */
    public function normalize(mixed $value, FieldDefinition $definition, FieldContext $context): array
    {
        $mapping = \is_string($value) ? ['src' => $value] : $this->mapping($value);
        $src = $mapping['src'] ?? null;
        if (!\is_string($src) || $src === '') {
            throw new FieldValueException('INVALID_MEDIA', 'Media reference requires a non-empty src.');
        }

        try {
            $relative = RelativePath::fromString($src);
        } catch (InvalidArgumentException $exception) {
            throw new FieldValueException('INVALID_MEDIA_PATH', $exception->getMessage());
        }
        if ($relative->isRoot() || str_starts_with(basename($src), '.') || basename($src) === 'content.yml') {
            throw new FieldValueException('INVALID_MEDIA_PATH', 'Media path is not allowed.');
        }
        if ($this->name === 'image') {
            $extension = strtolower(pathinfo($src, PATHINFO_EXTENSION));
            if (!\in_array($extension, self::IMAGE_EXTENSIONS, true)) {
                throw new FieldValueException('INVALID_IMAGE_EXTENSION', 'Image extension is not allowed.');
            }
        }

        $pageIdentity = $context->pageIdentity();
        if ($pageIdentity !== null) {
            try {
                $absolutePath = $this->paths->resolve(
                    FilesystemRoot::Pages,
                    RelativePath::fromString($pageIdentity->value() . '/' . $relative->value()),
                    mustExist: true,
                );
            } catch (FilesystemException|InvalidArgumentException) {
                throw new FieldValueException('MEDIA_NOT_FOUND', 'Referenced media file does not exist.');
            }
            if (!is_file($absolutePath)) {
                throw new FieldValueException('MEDIA_NOT_FOUND', 'Referenced media path is not a file.');
            }
        }

        $normalized = ['src' => $relative->value()];
        if ($this->name === 'image' && \array_key_exists('alt', $mapping)) {
            $normalized['alt'] = $this->alt($mapping['alt'], $context);
        }

        return $normalized;
    }

    public function localize(
        mixed $value,
        string $locale,
        FieldDefinition $definition,
        FieldContext $context,
    ): mixed {
        $mapping = $this->mapping($value);
        $alt = $mapping['alt'] ?? null;
        if (\is_array($alt)) {
            $mapping['alt'] = $alt[$locale] ?? $alt[$context->languages()->default()] ?? '';
        }

        return $mapping;
    }

    /** @return array<string, mixed> */
    private function mapping(mixed $value): array
    {
        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new FieldValueException('INVALID_MEDIA', 'Media value must be a mapping.');
        }

        $mapping = [];
        foreach ($value as $key => $item) {
            if (!\is_string($key) || !\in_array($key, ['src', 'alt'], true)) {
                throw new FieldValueException('INVALID_MEDIA', 'Media value contains an unknown property.');
            }

            $mapping[$key] = $item;
        }

        return $mapping;
    }

    /** @return array<string, string> */
    private function alt(mixed $value, FieldContext $context): array
    {
        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new FieldValueException('INVALID_ALT', 'Image alt must be a localized mapping.');
        }

        $alt = [];
        foreach ($value as $locale => $text) {
            if (!\is_string($locale) || !$context->languages()->has($locale) || !\is_string($text)) {
                throw new FieldValueException('INVALID_ALT', 'Image alt contains an invalid locale or value.');
            }

            $alt[$locale] = trim($text);
        }

        return $alt;
    }
}
