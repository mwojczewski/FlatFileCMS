<?php

declare(strict_types=1);

namespace FlatFileCms\Media;

final class MediaTypes
{
    /** @var array<string, string> */
    private const array EXTENSIONS = [
        'application/pdf' => 'pdf',
        'audio/mpeg' => 'mp3',
        'audio/ogg' => 'ogg',
        'audio/wav' => 'wav',
        'image/avif' => 'avif',
        'image/gif' => 'gif',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/svg+xml' => 'svg',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
    ];

    /** @return list<string> */
    public static function defaults(): array
    {
        return array_keys(self::EXTENSIONS);
    }

    public static function extension(string $mimeType): ?string
    {
        return self::EXTENSIONS[$mimeType] ?? null;
    }

    public static function isImage(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    public static function isTransformable(string $mimeType): bool
    {
        return \in_array($mimeType, ['image/avif', 'image/jpeg', 'image/png', 'image/webp'], true);
    }
}
