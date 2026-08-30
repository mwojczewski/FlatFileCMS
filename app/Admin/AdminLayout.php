<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Auth\CsrfTokenManager;
use FlatFileCms\Http\Response;

final readonly class AdminLayout
{
    private const string ASSET_VERSION = '10.0.2';

    public function __construct(
        private Authenticator $authenticator,
        private CsrfTokenManager $csrf,
    ) {}

    public function render(
        string $title,
        string $content,
        int $status = 200,
        string $active = '',
        bool $authScript = false,
        bool $builderScript = false,
        bool $markdownEditor = false,
    ): Response {
        $user = $this->authenticator->user();
        $authenticated = $user !== null;
        $styles = $markdownEditor
            ? $this->stylesheet('/assets/admin/vendor/easymde/easymde.min.css')
            : '';
        $scripts = $authScript ? $this->script('/assets/admin/auth.js') : '';
        if ($builderScript) {
            $scripts .= $this->script('/assets/admin/page-builder.js');
        }
        if ($markdownEditor) {
            $scripts .= $this->script('/assets/admin/vendor/easymde/easymde.min.js');
            $scripts .= $this->script('/assets/admin/markdown-editor.js');
        }

        $body = $authenticated
            ? $this->authenticatedBody($title, $content, $active, $user->email())
            : $this->authenticationBody($title, $content);

        return Response::html(
            '<!doctype html><html lang="pl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow">'
            . '<meta name="csrf-token" content="' . self::escape($this->csrf->token()) . '">'
            . '<title>' . self::escape($title) . ' — FlatFile CMS</title>'
            . $styles . $this->stylesheet('/assets/admin/admin.css')
            . $this->script('/assets/admin/admin.js') . $scripts
            . '</head><body class="' . ($authenticated ? 'admin-body' : 'auth-body') . '">'
            . $body . '</body></html>',
            $status,
            [
                'Cache-Control' => 'no-store',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
            ],
        );
    }

    private function authenticatedBody(string $title, string $content, string $active, string $email): string
    {
        return '<a class="skip-link" href="#admin-content">Przejdź do treści</a>'
            . '<div class="admin-shell"><div class="admin-backdrop" data-admin-backdrop></div>'
            . '<aside class="admin-sidebar" data-admin-sidebar><div class="admin-brand">'
            . '<a href="/admin" aria-label="FlatFile CMS — panel"><span class="admin-brand-mark">F</span>'
            . '<span><strong>FlatFile</strong><small>CMS</small></span></a>'
            . '<button class="sidebar-close" type="button" data-admin-menu-close aria-label="Zamknij menu">×</button></div>'
            . '<nav class="admin-navigation" aria-label="Nawigacja panelu">'
            . $this->navigationLink('/admin', 'dashboard', 'Pulpit', 'Przegląd systemu', $active)
            . $this->navigationLink('/admin/pages', 'pages', 'Strony', 'Treść i page builder', $active)
            . $this->navigationLink('/admin/security', 'account', 'Konto', 'Hasło i zabezpieczenia', $active)
            . '</nav><div class="sidebar-account"><span>' . self::escape($email) . '</span>'
            . '<form method="post" action="/admin/logout">' . $this->csrfField()
            . '<button type="submit" class="sidebar-logout">Wyloguj</button></form></div></aside>'
            . '<div class="admin-workspace"><header class="admin-topbar">'
            . '<button class="menu-toggle" type="button" data-admin-menu aria-label="Otwórz menu" aria-expanded="false">'
            . '<span></span><span></span><span></span></button><a class="mobile-brand" href="/admin">FlatFile CMS</a>'
            . '<form method="post" action="/admin/logout">' . $this->csrfField()
            . '<button type="submit" class="topbar-logout">Wyloguj</button></form></header>'
            . '<main class="admin-main" id="admin-content"><header class="page-heading"><div>'
            . '<p class="eyebrow">Panel administracyjny</p><h1>' . self::escape($title) . '</h1>'
            . '</div></header><div class="admin-content">' . $content . '</div></main></div></div>';
    }

    private function authenticationBody(string $title, string $content): string
    {
        return '<main class="auth-shell" id="admin-content"><section class="auth-card">'
            . '<a class="auth-brand" href="/admin/login"><span class="admin-brand-mark">F</span>'
            . '<span><strong>FlatFile</strong><small>CMS</small></span></a>'
            . '<div class="auth-heading"><p class="eyebrow">Bezpieczny panel</p><h1>' . self::escape($title) . '</h1></div>'
            . $content . '</section><p class="auth-caption">Treść w plikach. Kontrola w Twoich rękach.</p></main>';
    }

    private function navigationLink(
        string $url,
        string $name,
        string $label,
        string $description,
        string $active,
    ): string {
        $current = $active === $name;

        return '<a href="' . $url . '" class="' . ($current ? 'active' : '') . '"'
            . ($current ? ' aria-current="page"' : '') . '><span class="nav-indicator"></span><span><strong>'
            . $label . '</strong><small>' . $description . '</small></span></a>';
    }

    private function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::escape($this->csrf->token()) . '">';
    }

    private function stylesheet(string $path): string
    {
        return '<link rel="stylesheet" href="' . $path . '?v=' . self::ASSET_VERSION . '">';
    }

    private function script(string $path): string
    {
        return '<script src="' . $path . '?v=' . self::ASSET_VERSION . '" defer></script>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
