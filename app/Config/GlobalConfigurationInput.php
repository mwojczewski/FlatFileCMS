<?php

declare(strict_types=1);

namespace FlatFileCms\Config;

final readonly class GlobalConfigurationInput
{
    /**
     * @param array<string, string> $titleSuffix
     * @param array<string, string> $description
     * @param array<string, mixed> $openGraph
     * @param array<string, mixed> $twitter
     * @param array<mixed> $jsonLd
     * @param list<string> $allowedMimeTypes
     * @param list<string> $formats
     */
    public function __construct(
        public string $siteName,
        public string $siteUrl,
        public string $defaultLayout,
        public array $titleSuffix,
        public array $description,
        public ?string $ogImage,
        public array $openGraph,
        public array $twitter,
        public array $jsonLd,
        public int $maximumUploadBytes,
        public array $allowedMimeTypes,
        public bool $stripMetadata,
        public bool $transformationsEnabled,
        public bool $mediaCacheEnabled,
        public array $formats,
        public int $quality,
        public int $maximumWidth,
        public int $maximumHeight,
        public int $maximumPixels,
    ) {}
}
