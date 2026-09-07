<?php

/**
 * INSTRUKCJA — fragmenty do włączenia do config/routes.php.
 * Ten plik nie jest wykonywany samodzielnie.
 */

// 1. Dodaj do sekcji use:
use FlatFileCms\ApiDocs\ApiDocumentationController;

// 2. Wewnątrz `if ($container !== null)`, PRZED `/api/v1/{path*}`, dodaj:
$apiDocs = static fn(): ApiDocumentationController => $container->get(ApiDocumentationController::class);
$router->get(
    '/api/openapi.json',
    static fn(Request $request): Response => $apiDocs()->specification($request),
    'api.documentation.specification',
);
$router->get(
    '/api/docs',
    static fn(Request $request): Response => $apiDocs()->documentation($request),
    'api.documentation.ui',
);

