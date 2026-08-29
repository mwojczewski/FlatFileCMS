<?php

declare(strict_types=1);

use FlatFileCms\Core\Application;
use FlatFileCms\Core\Container;
use FlatFileCms\Core\Environment;
use FlatFileCms\Http\ErrorHandler;
use FlatFileCms\Http\Router;
use FlatFileCms\Infrastructure\Filesystem\AtomicFileWriter;
use FlatFileCms\Infrastructure\Filesystem\FileLockManager;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlFileCache;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;
use FlatFileCms\Infrastructure\Yaml\YamlParser;

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
        $container->get(Environment::class)->boolean('CACHE_ENABLED', true),
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
$container->set(Router::class, static function () use ($projectRoot): Router {
    $router = new Router();
    $registerRoutes = require $projectRoot . '/config/routes.php';
    if (!is_callable($registerRoutes)) {
        throw new RuntimeException('Route configuration must return a callable.');
    }

    $registerRoutes($router);

    return $router;
});
$container->set(ErrorHandler::class, static fn(Container $container): ErrorHandler => new ErrorHandler(
    debug: $container->get(Environment::class)->debug(),
));

return new Application(
    router: $container->get(Router::class),
    errorHandler: $container->get(ErrorHandler::class),
);
