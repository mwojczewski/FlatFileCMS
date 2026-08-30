<?php

declare(strict_types=1);

use FlatFileCms\Api\PublicApiController;
use FlatFileCms\Core\Container;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use FlatFileCms\Http\Router;
use FlatFileCms\Rendering\SiteController;

return static function (Router $router, ?Container $container = null): void {
    $router->get('/api/v1/health', static fn(Request $request): Response => Response::json([
        'status' => 'ok',
        'application' => 'FlatFile CMS',
        'stage' => 5,
    ]), 'api.health');

    if ($container !== null) {
        $router->get(
            '/api/v1/pages',
            static fn(Request $request): Response => $container->get(PublicApiController::class)->homepage($request),
            'api.pages.homepage',
        );
        $router->get(
            '/api/v1/pages/{path*}',
            static fn(Request $request): Response => $container->get(PublicApiController::class)->page($request),
            'api.pages.show',
        );
        $router->get(
            '/api/v1/navigation',
            static fn(Request $request): Response => $container->get(PublicApiController::class)->navigation($request),
            'api.navigation',
        );
        $router->get(
            '/api/v1/config',
            static fn(Request $request): Response => $container->get(PublicApiController::class)->configuration($request),
            'api.config',
        );
        $router->get(
            '/api/v1/{path*}',
            static function (Request $request): Response {
                throw new HttpException(404, 'ROUTE_NOT_FOUND', 'Route not found');
            },
            'api.missing',
        );
        $router->get(
            '/api/v1',
            static function (Request $request): Response {
                throw new HttpException(404, 'ROUTE_NOT_FOUND', 'Route not found');
            },
            'api.root.missing',
        );
    }

    $router->get('/admin', static fn(Request $request): Response => Response::html(
        '<!doctype html><html lang="pl"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>FlatFile CMS — panel</title>'
        . '</head><body><main><h1>FlatFile CMS</h1>'
        . '<p>Panel administracyjny zostanie udostępniony po wdrożeniu warstwy uwierzytelniania.</p>'
        . '</main></body></html>',
        status: 503,
        headers: ['Retry-After' => '3600'],
    ), 'admin.entry');

    if ($container === null) {
        $router->get('/', static fn(Request $request): Response => Response::html(
            '<!doctype html><html lang="pl"><head><meta charset="utf-8"><title>FlatFile CMS</title>'
            . '</head><body><main><h1>FlatFile CMS</h1></main></body></html>',
        ), 'site.home');

        return;
    }

    $router->get(
        '/',
        static fn(Request $request): Response => $container->get(SiteController::class)->homepage($request),
        'site.home',
    );
    $router->get(
        '/{path*}',
        static fn(Request $request): Response => $container->get(SiteController::class)->page($request),
        'site.page',
    );
};
