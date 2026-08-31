<?php

declare(strict_types=1);

use FlatFileCms\Admin\AdminAuthController;
use FlatFileCms\Admin\AdminCollectionController;
use FlatFileCms\Admin\AdminMediaController;
use FlatFileCms\Admin\AdminPageBuilderController;
use FlatFileCms\Admin\AdminPageController;
use FlatFileCms\Admin\AdminRedirectController;
use FlatFileCms\Admin\AdminSettingsController;
use FlatFileCms\Admin\AdminUserController;
use FlatFileCms\Admin\PasswordResetController;
use FlatFileCms\Api\PublicApiController;
use FlatFileCms\Core\Container;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use FlatFileCms\Http\Router;
use FlatFileCms\Media\PublicMediaController;
use FlatFileCms\Redirects\RedirectController;
use FlatFileCms\Rendering\SiteController;
use FlatFileCms\Seo\SitemapController;
use FlatFileCms\Seo\SiteTextController;

return static function (Router $router, ?Container $container = null): void {
    $router->get('/api/v1/health', static fn(Request $request): Response => Response::json([
        'status' => 'ok',
        'application' => 'FlatFile CMS',
        'stage' => 13,
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
        $media = static fn(): AdminMediaController => $container->get(AdminMediaController::class);
        $collections = static fn(): AdminCollectionController => $container->get(AdminCollectionController::class);
        $users = static fn(): AdminUserController => $container->get(AdminUserController::class);
        $settings = static fn(): AdminSettingsController => $container->get(AdminSettingsController::class);
        $redirectSettings = static fn(): AdminRedirectController => $container->get(AdminRedirectController::class);
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
        $router->get('/admin/account/security-keys', static fn(Request $request): Response => $admin()->securityKeys($request), 'admin.security.keys');
        $router->post('/admin/account/security-keys/delete', static fn(Request $request): Response => $admin()->deleteSecurityKey($request), 'admin.security.keys.delete');
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
        $router->get('/admin/collections/edit', static fn(Request $request): Response => $collections()->edit($request), 'admin.collections.edit');
        $router->post('/admin/collections/update', static fn(Request $request): Response => $collections()->update($request), 'admin.collections.update');
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
        $router->get('/admin/media', static fn(Request $request): Response => $media()->index($request), 'admin.media.index');
        $router->get('/admin/media/picker', static fn(Request $request): Response => $media()->picker($request), 'admin.media.picker');
        $router->post('/admin/media/upload', static fn(Request $request): Response => $media()->upload($request), 'admin.media.upload');
        $router->post('/admin/media/delete', static fn(Request $request): Response => $media()->delete($request), 'admin.media.delete');
        $router->get('/admin/navigation', static fn(Request $request): Response => $settings()->navigation($request), 'admin.navigation');
        $router->post('/admin/navigation', static fn(Request $request): Response => $settings()->updateNavigation($request), 'admin.navigation.update');
        $router->get('/admin/settings', static fn(Request $request): Response => $settings()->configuration($request), 'admin.settings');
        $router->post('/admin/settings', static fn(Request $request): Response => $settings()->updateConfiguration($request), 'admin.settings.update');
        $router->post('/admin/settings/llms', static fn(Request $request): Response => $settings()->updateLlms($request), 'admin.settings.llms.update');
        $router->post('/admin/settings/security-text', static fn(Request $request): Response => $settings()->updateSecurityText($request), 'admin.settings.security.update');
        $router->get('/admin/redirects', static fn(Request $request): Response => $redirectSettings()->index($request), 'admin.redirects.index');
        $router->post('/admin/redirects/create', static fn(Request $request): Response => $redirectSettings()->create($request), 'admin.redirects.create');
        $router->post('/admin/redirects/update', static fn(Request $request): Response => $redirectSettings()->update($request), 'admin.redirects.update');
        $router->post('/admin/redirects/delete', static fn(Request $request): Response => $redirectSettings()->delete($request), 'admin.redirects.delete');
        $router->get('/admin/users', static fn(Request $request): Response => $users()->index($request), 'admin.users.index');
        $router->get('/admin/users/create', static fn(Request $request): Response => $users()->createForm($request), 'admin.users.create.form');
        $router->post('/admin/users/create', static fn(Request $request): Response => $users()->create($request), 'admin.users.create');
        $router->get('/admin/users/edit', static fn(Request $request): Response => $users()->editForm($request), 'admin.users.edit');
        $router->post('/admin/users/update', static fn(Request $request): Response => $users()->update($request), 'admin.users.update');
        $router->post('/admin/users/delete', static fn(Request $request): Response => $users()->delete($request), 'admin.users.delete');
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
        '/media/{path*}',
        static fn(Request $request): Response => $container->get(PublicMediaController::class)->show($request),
        'site.media',
    );

    $router->get('/sitemap.xml', static fn(Request $request): Response => $container->get(SitemapController::class)->show($request), 'site.sitemap');
    $router->get('/llms.txt', static fn(Request $request): Response => $container->get(SiteTextController::class)->llms($request), 'site.llms');
    $router->get('/lms.txt', static fn(Request $request): Response => $container->get(SiteTextController::class)->llms($request), 'site.lms.alias');
    $router->get('/security.txt', static fn(Request $request): Response => $container->get(SiteTextController::class)->security($request), 'site.security');
    $router->get('/.well-known/security.txt', static fn(Request $request): Response => $container->get(SiteTextController::class)->security($request), 'site.security.well-known');

    $redirects = static fn(): RedirectController => $container->get(RedirectController::class);
    $site = static fn(): SiteController => $container->get(SiteController::class);

    $router->get(
        '/',
        static fn(Request $request): Response => $redirects()->resolve($request) ?? $site()->homepage($request),
        'site.home',
    );
    $router->get(
        '/{path*}',
        static fn(Request $request): Response => $redirects()->resolve($request) ?? $site()->page($request),
        'site.page',
    );
};
