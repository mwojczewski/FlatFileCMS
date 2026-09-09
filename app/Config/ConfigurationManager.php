<?php

declare(strict_types=1);

namespace FlatFileCms\Config;

use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Rendering\LayoutRegistry;
use InvalidArgumentException;

final readonly class ConfigurationManager
{
    public function __construct(
        private ConfigurationRepository $configuration,
        private LayoutRegistry $layouts,
    ) {}

    public function editable(): ConfigurationDocument
    {
        return $this->configuration->get();
    }

    public function update(GlobalConfigurationInput $input, FileRevision $revision): ConfigurationDocument
    {
        if (!isset($this->layouts->all()[$input->defaultLayout])) {
            throw new InvalidArgumentException('Selected default layout does not exist.');
        }

        $current = $this->configuration->get()->data();
        $current['schemaVersion'] = 1;
        $site = $this->mapping($current['site'] ?? []);
        $current['site'] = [...$site, ...[
            'name' => $input->siteName,
            'url' => rtrim($input->siteUrl, '/'),
            'defaultLayout' => $input->defaultLayout,
        ]];
        $seo = $this->mapping($current['seo'] ?? []);
        $current['seo'] = [...$seo, ...[
            'titleSuffix' => $input->titleSuffix,
            'description' => $input->description,
            'ogImage' => $input->ogImage,
            'openGraph' => $input->openGraph,
            'twitter' => $input->twitter,
            'jsonLd' => $input->jsonLd,
        ]];
        $media = $this->mapping($current['media'] ?? []);
        $transformations = $this->mapping($media['transformations'] ?? []);
        $cache = $this->mapping($media['cache'] ?? []);
        $current['media'] = [...$media, ...[
            'maxUploadBytes' => $input->maximumUploadBytes,
            'allowedMimeTypes' => $input->allowedMimeTypes,
            'stripMetadata' => $input->stripMetadata,
            'transformations' => [...$transformations, ...[
                'enabled' => $input->transformationsEnabled,
                'quality' => $input->quality,
                'maxWidth' => $input->maximumWidth,
                'maxHeight' => $input->maximumHeight,
                'maxPixels' => $input->maximumPixels,
            ]],
            'cache' => [...$cache, ...['enabled' => $input->mediaCacheEnabled]],
            'formats' => $input->formats,
        ]];

        return $this->configuration->update($current, $revision);
    }

    /** @return array<string, mixed> */
    private function mapping(mixed $value): array
    {
        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            return [];
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (\is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
