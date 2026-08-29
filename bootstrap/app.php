<?php

declare(strict_types=1);

use FlatFileCms\Core\Application;
use FlatFileCms\Core\Container;
use FlatFileCms\Core\Environment;
use FlatFileCms\Http\ErrorHandler;
use FlatFileCms\Http\Router;

$projectRoot = dirname(__DIR__);

require $projectRoot . '/vendor/autoload.php';

$environment = Environment::load($projectRoot);
$container = new Container();
$container->set(Environment::class, static fn(): Environment => $environment);
$container->set(Router::class, static function () use ($projectRoot): Router {
    $router = new Router();
    $registerRoutes = require $projectRoot . '/config/routes.php';
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
