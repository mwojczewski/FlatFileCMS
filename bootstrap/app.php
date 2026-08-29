<?php

declare(strict_types=1);

use FlatFileCms\Api\ApiResponseFactory;
use FlatFileCms\Api\PageSerializer;
use FlatFileCms\Api\PublicApiController;
use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Core\Application;
use FlatFileCms\Core\Container;
use FlatFileCms\Core\Environment;
use FlatFileCms\Domain\Localization\LocalizedDataResolver;
use FlatFileCms\Http\ErrorHandler;
use FlatFileCms\Http\Router;
use FlatFileCms\Infrastructure\Filesystem\AtomicFileWriter;
use FlatFileCms\Infrastructure\Filesystem\FileLockManager;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlFileCache;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;
use FlatFileCms\Infrastructure\Yaml\YamlParser;
use FlatFileCms\Navigation\NavigationRepository;
use FlatFileCms\Seo\SeoResolver;

$projectRoot = dirname(__DIR__);

require $projectRoot . '/vendor/autoload.php';

$environment = Environment::load($projectRoot);
$container = new Container();
$container->set(Environment::class, static fn(): Environment => $environment);
$container->set(
    SafePathResolver::class,
    static fn(Container $container): SafePathResolver => new SafePathResolver(
        $container->get(Environment::class)->projectRoot(),
    ),
);
$container->set(
    FileLockManager::class,
    static fn(Container $container): FileLockManager => new FileLockManager(
        $container->get(SafePathResolver::class),
    ),
);
$container->set(
    AtomicFileWriter::class,
    static fn(Container $container): AtomicFileWriter => new AtomicFileWriter(
        $container->get(SafePathResolver::class),
        $container->get(FileLockManager::class),
    ),
);
$container->set(YamlParser::class, static fn(): YamlParser => new YamlParser());
$container->set(
    YamlFileCache::class,
    static fn(Container $container): YamlFileCache => new YamlFileCache(
        $container->get(Environment::class)->boolean('YAML_CACHE_ENABLED', true),
        $container->get(SafePathResolver::class),
        $container->get(AtomicFileWriter::class),
    ),
);
$container->set(
    YamlFileRepository::class,
    static fn(Container $container): YamlFileRepository => new YamlFileRepository(
        $container->get(SafePathResolver::class),
        $container->get(YamlParser::class),
        $container->get(YamlFileCache::class),
        $container->get(AtomicFileWriter::class),
    ),
);
$container->set(
    LanguageRepository::class,
    static fn(Container $container): LanguageRepository => new LanguageRepository(
        $container->get(YamlFileRepository::class),
        $container->get(SafePathResolver::class),
    ),
);
$container->set(
    ConfigurationRepository::class,
    static fn(Container $container): ConfigurationRepository => new ConfigurationRepository(
        $container->get(YamlFileRepository::class),
        $container->get(SafePathResolver::class),
    ),
);
$container->set(
    PageRepository::class,
    static fn(Container $container): PageRepository => new PageRepository(
        $container->get(YamlFileRepository::class),
        $container->get(SafePathResolver::class),
    ),
);
$container->set(LocalizedDataResolver::class, static fn(): LocalizedDataResolver => new LocalizedDataResolver());
$container->set(
    NavigationRepository::class,
    static fn(Container $container): NavigationRepository => new NavigationRepository(
        $container->get(YamlFileRepository::class),
        $container->get(SafePathResolver::class),
        $container->get(LocalizedDataResolver::class),
    ),
);
$container->set(
    SeoResolver::class,
    static fn(Container $container): SeoResolver => new SeoResolver(
        $container->get(LocalizedDataResolver::class),
    ),
);
$container->set(
    PageSerializer::class,
    static fn(Container $container): PageSerializer => new PageSerializer(
        $container->get(LocalizedDataResolver::class),
        $container->get(SeoResolver::class),
    ),
);
$container->set(ApiResponseFactory::class, static fn(): ApiResponseFactory => new ApiResponseFactory());
$container->set(
    PublicApiController::class,
    static fn(Container $container): PublicApiController => new PublicApiController(
        $container->get(LanguageRepository::class),
        $container->get(ConfigurationRepository::class),
        $container->get(PageRepository::class),
        $container->get(NavigationRepository::class),
        $container->get(LocalizedDataResolver::class),
        $container->get(PageSerializer::class),
        $container->get(ApiResponseFactory::class),
    ),
);
$container->set(Router::class, static function (Container $container) use ($projectRoot): Router {
    $router = new Router();
    $registerRoutes = require $projectRoot . '/config/routes.php';
    if (!is_callable($registerRoutes)) {
        throw new RuntimeException('Route configuration must return a callable.');
    }

    $registerRoutes($router, $container);

    return $router;
});
$container->set(ErrorHandler::class, static fn(Container $container): ErrorHandler => new ErrorHandler(
    debug: $container->get(Environment::class)->debug(),
));

return new Application(
    router: $container->get(Router::class),
    errorHandler: $container->get(ErrorHandler::class),
);
