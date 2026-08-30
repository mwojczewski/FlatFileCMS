<?php

declare(strict_types=1);

use FlatFileCms\Admin\AdminAuthController;
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
        'stage' => 7,
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
            '/api/v1/collections/{path*}',
            static fn(Request $request): Response => $container->get(PublicApiController::class)->collection($request),
            'api.collections.show',
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

    if ($container !== null) {
        $admin = static fn(): AdminAuthController => $container->get(AdminAuthController::class);
        $router->get('/admin/login', static fn(Request $request): Response => $admin()->loginForm($request), 'admin.login.form');
        $router->post('/admin/login', static fn(Request $request): Response => $admin()->login($request), 'admin.login');
        $router->get('/admin/2fa', static fn(Request $request): Response => $admin()->secondFactor($request), 'admin.2fa');
        $router->post('/admin/webauthn/authentication/options', static fn(Request $request): Response => $admin()->authenticationOptions($request), 'admin.webauthn.authentication.options');
        $router->post('/admin/webauthn/authentication/verify', static fn(Request $request): Response => $admin()->authenticationVerify($request), 'admin.webauthn.authentication.verify');
        $router->get('/admin/security', static fn(Request $request): Response => $admin()->security($request), 'admin.security');
        $router->post('/admin/security/webauthn/registration/options', static fn(Request $request): Response => $admin()->registrationOptions($request), 'admin.webauthn.registration.options');
        $router->post('/admin/security/webauthn/registration/verify', static fn(Request $request): Response => $admin()->registrationVerify($request), 'admin.webauthn.registration.verify');
        $router->post('/admin/logout', static fn(Request $request): Response => $admin()->logout($request), 'admin.logout');
        $router->get('/admin', static fn(Request $request): Response => $admin()->dashboard($request), 'admin.entry');
    }

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
