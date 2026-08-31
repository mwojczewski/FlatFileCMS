<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Auth\CsrfTokenManager;
use FlatFileCms\Http\Response;

final readonly class AdminLayout
{
    private const string ASSET_VERSION = '13.0.0';

    public function __construct(
        private Authenticator $authenticator,
        private CsrfTokenManager $csrf,
        private AdminView $views,
    ) {}

    public function render(
        string $title,
        string $content,
        int $status = 200,
        string $active = '',
        bool $authScript = false,
        bool $builderScript = false,
        bool $markdownEditor = false,
        bool $settingsScript = false,
        bool $pageFormScript = false,
    ): Response {
        $user = $this->authenticator->user();
        $authenticated = $user !== null;
        $styles = "";
        if ($markdownEditor) {
            $styles .= $this->stylesheet('/assets/admin/vendor/easymde/easymde.min.css');
            $styles .= $this->stylesheet('/assets/admin/font-awesome.min.css');
        }
        $scripts = $authScript ? $this->script('/assets/admin/auth.js') : '';
        if ($builderScript) {
            $scripts .= $this->script('/assets/admin/page-builder.js');
        }
        if ($markdownEditor) {
            $scripts .= $this->script('/assets/admin/vendor/easymde/easymde.min.js');
            $scripts .= $this->script('/assets/admin/markdown-editor.js');
        }
        if ($settingsScript) {
            $scripts .= $this->script('/assets/admin/navigation-editor.js');
        }
        if ($pageFormScript) {
            $scripts .= $this->script('/assets/admin/page-form.js');
        }

        $bodyView = $authenticated ? 'layout/authenticated' : 'layout/authentication';
        $body = $this->views->render($bodyView, [
            'title' => $title,
            'content' => $content,
            'active' => $active,
            'email' => $user?->email() ?? '',
            'csrfToken' => $this->csrf->token(),
        ]);

        return Response::html(
            $this->views->render('layout/document', [
                'title' => $title,
                'csrfToken' => $this->csrf->token(),
                'styles' => $styles . $this->stylesheet('/assets/admin/admin.css'),
                'scripts' => $this->script('/assets/admin/admin.js') . $scripts,
                'bodyClass' => $authenticated ? 'admin-body' : 'auth-body',
                'body' => $body,
            ]),
            $status,
            [
                'Cache-Control' => 'no-store',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'Referrer-Policy' => 'no-referrer',
                'Permissions-Policy' => 'camera=(), geolocation=(), microphone=()',
                'Content-Security-Policy' => "default-src 'self'; base-uri 'none'; frame-ancestors 'none'; "
                    . "form-action 'self'; object-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; "
                    . "script-src 'self'; connect-src 'self'",
            ],
        );
    }

    private function stylesheet(string $path): string
    {
        return '<link rel="stylesheet" href="' . $path . '?v=' . self::ASSET_VERSION . '">';
    }

    private function script(string $path): string
    {
        return '<script src="' . $path . '?v=' . self::ASSET_VERSION . '" defer></script>';
    }

}
