<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Support;

use FlatFileCms\Api\ApiResponseFactory;
use FlatFileCms\Api\PageSerializer;
use FlatFileCms\Api\PublicApiController;
use FlatFileCms\Blocks\BlockProcessor;
use FlatFileCms\Blocks\BlockRegistry;
use FlatFileCms\Blocks\BlockValidator;
use FlatFileCms\Blocks\BuiltinFieldTypes;
use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Domain\Localization\LocalizedDataResolver;
use FlatFileCms\Infrastructure\Filesystem\AtomicFileWriter;
use FlatFileCms\Infrastructure\Filesystem\FileLockManager;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlFileCache;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;
use FlatFileCms\Infrastructure\Yaml\YamlParser;
use FlatFileCms\Navigation\NavigationRepository;
use FlatFileCms\Seo\SeoResolver;

final class TestContentFactory
{
    public static function yaml(TemporaryProject $project): YamlFileRepository
    {
        $paths = new SafePathResolver($project->path());
        $writer = new AtomicFileWriter($paths, new FileLockManager($paths));

        return new YamlFileRepository(
            $paths,
            new YamlParser(),
            new YamlFileCache(false, $paths, $writer),
            $writer,
        );
    }

    public static function publicApi(TemporaryProject $project): PublicApiController
    {
        $paths = new SafePathResolver($project->path());
        $writer = new AtomicFileWriter($paths, new FileLockManager($paths));
        $yaml = new YamlFileRepository(
            $paths,
            new YamlParser(),
            new YamlFileCache(false, $paths, $writer),
            $writer,
        );
        $localization = new LocalizedDataResolver();
        $seo = new SeoResolver($localization);
        $fieldTypes = BuiltinFieldTypes::create($paths);
        $blockRegistry = new BlockRegistry($project->path(), new YamlParser(), $fieldTypes);
        $blockProcessor = new BlockProcessor($blockRegistry, new BlockValidator($fieldTypes));

        return new PublicApiController(
            new LanguageRepository($yaml, $paths),
            new ConfigurationRepository($yaml, $paths),
            new PageRepository($yaml, $paths),
            new NavigationRepository($yaml, $paths, $localization),
            $localization,
            new PageSerializer($blockProcessor, $seo),
            new ApiResponseFactory(),
        );
    }
}
