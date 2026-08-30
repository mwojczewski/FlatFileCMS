<?php

declare(strict_types=1);

namespace FlatFileCms\Config;

use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;
use FlatFileCms\Support\ContentData;
use InvalidArgumentException;

final readonly class ConfigurationRepository
{
    private const string FILE = 'setup.yml';

    public function __construct(
        private YamlFileRepository $yaml,
        private SafePathResolver $paths,
    ) {}

    public function get(): ConfigurationDocument
    {
        $path = RelativePath::fromString(self::FILE);
        $document = $this->yaml->read(FilesystemRoot::Config, $path);
        $absolutePath = $this->paths->resolve(FilesystemRoot::Config, $path, mustExist: true);
        clearstatcache(true, $absolutePath);
        $modifiedAt = filemtime($absolutePath);
        if ($modifiedAt === false) {
            throw new InvalidContentException('Unable to read setup.yml modification time.');
        }

        try {
            $data = $document->data();
            if (ContentData::integer($data['schemaVersion'] ?? null, 'schemaVersion') !== 1) {
                throw new InvalidArgumentException('Unsupported setup schema version.');
            }

            $site = ContentData::map($data['site'] ?? null, 'site');
            ContentData::string($site['name'] ?? null, 'site.name');
            $url = ContentData::string($site['url'] ?? null, 'site.url');
            if (
                filter_var($url, FILTER_VALIDATE_URL) === false
                || !\in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            ) {
                throw new InvalidArgumentException('site.url must be an absolute HTTP or HTTPS URL.');
            }

            $layout = ContentData::string($site['defaultLayout'] ?? null, 'site.defaultLayout');
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $layout) !== 1) {
                throw new InvalidArgumentException('Default layout name is invalid.');
            }

            ContentData::map($data['seo'] ?? [], 'seo');
            ContentData::map($data['media'] ?? [], 'media');

            return new ConfigurationDocument($data, $document->revision(), $modifiedAt);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidContentException('Invalid setup.yml configuration.', previous: $exception);
        }
    }

    /** @return array<string, mixed> */
    public function publicProjection(ConfigurationDocument $configuration): array
    {
        $data = $configuration->data();

        return [
            'site' => $this->mappingOrEmpty($data['site'] ?? []),
            'seo' => $this->mappingOrEmpty($data['seo'] ?? []),
            'media' => $this->mappingOrEmpty($data['media'] ?? []),
        ];
    }

    /** @return array<string, mixed> */
    private function mappingOrEmpty(mixed $value): array
    {
        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new InvalidContentException('Public setup configuration sections must be mappings.');
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw new InvalidContentException('Public setup configuration uses a non-string key.');
            }

            $result[$key] = $item;
        }

        return $result;
    }
}
