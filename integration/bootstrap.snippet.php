<?php

/**
 * INSTRUKCJA — fragmenty do włączenia do bootstrap/app.php.
 * Ten plik nie jest wykonywany samodzielnie.
 */

// 1. Dodaj do sekcji use:
use FlatFileCms\ApiDocs\ApiDocumentationController;
use FlatFileCms\ApiDocs\OpenApiDocument;

// 2. Dodaj po utworzeniu kontenera i zarejestrowaniu Environment:
$container->set(
    OpenApiDocument::class,
    static fn(Container $container): OpenApiDocument => new OpenApiDocument(
        $container->get(Environment::class)->projectRoot() . '/docs/openapi.yaml',
    ),
);
$container->set(
    ApiDocumentationController::class,
    static fn(Container $container): ApiDocumentationController => new ApiDocumentationController(
        $container->get(OpenApiDocument::class),
    ),
);

