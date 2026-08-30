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
use FlatFileCms\Http\HtmlResponseFactory;
use FlatFileCms\Infrastructure\Filesystem\AtomicFileWriter;
use FlatFileCms\Infrastructure\Filesystem\FileLockManager;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlFileCache;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;
use FlatFileCms\Infrastructure\Yaml\YamlParser;
use FlatFileCms\Navigation\NavigationRepository;
use FlatFileCms\Presentation\PageViewModelFactory;
use FlatFileCms\Rendering\AssetCollector;
use FlatFileCms\Rendering\AssetPublisher;
use FlatFileCms\Rendering\BlockRenderer;
use FlatFileCms\Rendering\LayoutRegistry;
use FlatFileCms\Rendering\LayoutRenderer;
use FlatFileCms\Rendering\MarkdownRenderer;
use FlatFileCms\Rendering\OutputBuffer;
use FlatFileCms\Rendering\PageRenderer;
use FlatFileCms\Rendering\PartialRegistry;
use FlatFileCms\Rendering\PartialRenderer;
use FlatFileCms\Rendering\SiteController;
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
            new PageSerializer(new PageViewModelFactory($blockProcessor, $seo)),
            new ApiResponseFactory(),
        );
    }

    public static function site(TemporaryProject $project): SiteController
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
        $fieldTypes = BuiltinFieldTypes::create($paths);
        $registry = new BlockRegistry($project->path(), new YamlParser(), $fieldTypes);
        $blocks = new BlockProcessor($registry, new BlockValidator($fieldTypes));
        $views = new PageViewModelFactory($blocks, new SeoResolver($localization));
        $buffer = new OutputBuffer();
        $partials = new PartialRenderer(new PartialRegistry($project->path()), $buffer);
        $renderer = new PageRenderer(
            new BlockRenderer($registry, $buffer),
            new LayoutRenderer(new LayoutRegistry($project->path()), $buffer),
            new AssetCollector($registry, new AssetPublisher($project->path())),
            new MarkdownRenderer(),
            $partials,
        );

        return new SiteController(
            new LanguageRepository($yaml, $paths),
            new ConfigurationRepository($yaml, $paths),
            new PageRepository($yaml, $paths),
            new NavigationRepository($yaml, $paths, $localization),
            $views,
            $renderer,
            new HtmlResponseFactory(),
        );
    }
}
