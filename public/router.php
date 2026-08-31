<?php

declare(strict_types=1);

// Development router for PHP's built-in web server. Existing public files
// must be handled directly; every other request goes through the CMS kernel.
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = \is_string($requestUri) ? \parse_url($requestUri, PHP_URL_PATH) : null;
if (\is_string($requestPath)) {
    $publicRoot = \realpath(__DIR__);
    $candidate = \realpath(__DIR__ . '/' . \ltrim($requestPath, '/'));

    if (
        $publicRoot !== false
        && $candidate !== false
        && \is_file($candidate)
        && \str_starts_with($candidate, $publicRoot . DIRECTORY_SEPARATOR)
    ) {
        return false;
    }
}

require __DIR__ . '/index.php';
