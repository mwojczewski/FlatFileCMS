<?php

declare(strict_types=1);

use FlatFileCms\Admin\AdminAuthController;
use FlatFileCms\Admin\AdminPageBuilderController;
use FlatFileCms\Admin\AdminPageController;
use FlatFileCms\Admin\PasswordResetController;
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
        'stage' => 10,
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
        $pages = static fn(): AdminPageController => $container->get(AdminPageController::class);
        $builder = static fn(): AdminPageBuilderController => $container->get(AdminPageBuilderController::class);
        $passwordReset = static fn(): PasswordResetController => $container->get(PasswordResetController::class);
        $router->get('/admin/login', static fn(Request $request): Response => $admin()->loginForm($request), 'admin.login.form');
        $router->post('/admin/login', static fn(Request $request): Response => $admin()->login($request), 'admin.login');
        $router->get('/admin/password/forgot', static fn(Request $request): Response => $passwordReset()->requestForm($request), 'admin.password.forgot.form');
        $router->post('/admin/password/forgot', static fn(Request $request): Response => $passwordReset()->request($request), 'admin.password.forgot');
        $router->get('/admin/password/reset', static fn(Request $request): Response => $passwordReset()->resetForm($request), 'admin.password.reset.form');
        $router->post('/admin/password/reset', static fn(Request $request): Response => $passwordReset()->reset($request), 'admin.password.reset');
        $router->get('/admin/2fa', static fn(Request $request): Response => $admin()->secondFactor($request), 'admin.2fa');
        $router->post('/admin/webauthn/authentication/options', static fn(Request $request): Response => $admin()->authenticationOptions($request), 'admin.webauthn.authentication.options');
        $router->post('/admin/webauthn/authentication/verify', static fn(Request $request): Response => $admin()->authenticationVerify($request), 'admin.webauthn.authentication.verify');
        $router->get('/admin/security', static fn(Request $request): Response => $admin()->security($request), 'admin.security');
        $router->get('/admin/account/password', static fn(Request $request): Response => $admin()->passwordForm($request), 'admin.password.form');
        $router->post('/admin/account/password', static fn(Request $request): Response => $admin()->changePassword($request), 'admin.password.change');
        $router->post('/admin/security/webauthn/registration/options', static fn(Request $request): Response => $admin()->registrationOptions($request), 'admin.webauthn.registration.options');
        $router->post('/admin/security/webauthn/registration/verify', static fn(Request $request): Response => $admin()->registrationVerify($request), 'admin.webauthn.registration.verify');
        $router->post('/admin/logout', static fn(Request $request): Response => $admin()->logout($request), 'admin.logout');
        $router->get('/admin/pages', static fn(Request $request): Response => $pages()->index($request), 'admin.pages.index');
        $router->get('/admin/pages/create', static fn(Request $request): Response => $pages()->createForm($request), 'admin.pages.create.form');
        $router->post('/admin/pages/create', static fn(Request $request): Response => $pages()->create($request), 'admin.pages.create');
        $router->get('/admin/pages/edit', static fn(Request $request): Response => $pages()->editForm($request), 'admin.pages.edit');
        $router->post('/admin/pages/update', static fn(Request $request): Response => $pages()->update($request), 'admin.pages.update');
        $router->post('/admin/pages/move', static fn(Request $request): Response => $pages()->move($request), 'admin.pages.move');
        $router->post('/admin/pages/delete', static fn(Request $request): Response => $pages()->delete($request), 'admin.pages.delete');
        $router->get('/admin/pages/builder', static fn(Request $request): Response => $builder()->index($request), 'admin.builder.index');
        $router->get('/admin/pages/builder/picker', static fn(Request $request): Response => $builder()->picker($request), 'admin.builder.picker');
        $router->get('/admin/pages/builder/preview', static fn(Request $request): Response => $builder()->preview($request), 'admin.builder.preview');
        $router->get('/admin/pages/builder/create', static fn(Request $request): Response => $builder()->createForm($request), 'admin.builder.create.form');
        $router->post('/admin/pages/builder/create', static fn(Request $request): Response => $builder()->create($request), 'admin.builder.create');
        $router->get('/admin/pages/builder/edit', static fn(Request $request): Response => $builder()->editForm($request), 'admin.builder.edit.form');
        $router->post('/admin/pages/builder/update', static fn(Request $request): Response => $builder()->update($request), 'admin.builder.update');
        $router->post('/admin/pages/builder/duplicate', static fn(Request $request): Response => $builder()->duplicate($request), 'admin.builder.duplicate');
        $router->post('/admin/pages/builder/toggle', static fn(Request $request): Response => $builder()->toggle($request), 'admin.builder.toggle');
        $router->post('/admin/pages/builder/reorder', static fn(Request $request): Response => $builder()->reorder($request), 'admin.builder.reorder');
        $router->post('/admin/pages/builder/delete', static fn(Request $request): Response => $builder()->delete($request), 'admin.builder.delete');
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
