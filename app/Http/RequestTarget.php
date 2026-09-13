<?php

declare(strict_types=1);

namespace FlatFileCms\Http;

use Uri\Rfc3986\Uri;

final class RequestTarget
{
    public static function path(string $target): string
    {
        $uri = Uri::parse($target);
        if ($uri === null || $uri->getScheme() !== null || $uri->getHost() !== null || $uri->getFragment() !== null) {
            return '/';
        }

        $path = $uri->getRawPath();

        return $path === '' ? '/' : $path;
    }
}
