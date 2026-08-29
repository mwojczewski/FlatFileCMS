<?php

declare(strict_types=1);

namespace FlatFileCms\Config;

use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;
use FlatFileCms\Support\ContentData;
use InvalidArgumentException;

final readonly class LanguageRepository
{
    private const string FILE = 'languages.yml';

    public function __construct(
        private YamlFileRepository $yaml,
        private SafePathResolver $paths,
    ) {}

    public function get(): LanguageConfig
    {
        return $this->document()->config();
    }

    public function document(): LanguageDocument
    {
        $path = RelativePath::fromString(self::FILE);
        $document = $this->yaml->read(FilesystemRoot::Config, $path);
        $data = $document->data();

        try {
            $default = ContentData::string($data['default'] ?? null, 'default');
            $definitions = ContentData::map($data['languages'] ?? null, 'languages');
            $enabled = [];

            foreach ($definitions as $code => $definition) {
                $language = ContentData::map($definition, 'languages.' . $code);
                if (!ContentData::boolean($language['enabled'] ?? true, 'languages.' . $code . '.enabled')) {
                    continue;
                }

                $enabled[$code] = ContentData::string($language['name'] ?? null, 'languages.' . $code . '.name');
            }

            if ($enabled === []) {
                throw new InvalidArgumentException('At least one language must be enabled.');
            }

            /** @var non-empty-array<string, string> $enabled */
            $config = new LanguageConfig($default, $enabled);
            $absolutePath = $this->paths->resolve(FilesystemRoot::Config, $path, mustExist: true);
            clearstatcache(true, $absolutePath);
            $modifiedAt = filemtime($absolutePath);
            if ($modifiedAt === false) {
                throw new InvalidArgumentException('Unable to read languages.yml modification time.');
            }

            return new LanguageDocument($config, $document->revision(), $modifiedAt);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidContentException('Invalid languages.yml configuration.', previous: $exception);
        }
    }
}
