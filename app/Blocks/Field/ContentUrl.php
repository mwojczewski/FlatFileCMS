<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks\Field;

use FlatFileCms\Http\PublicHttpUrl;
use InvalidArgumentException;
use Uri\Rfc3986\Uri;

final class ContentUrl
{
    public static function isValid(string $value): bool
    {
        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
            $uri = Uri::parse($value);

            return $uri !== null && $uri->getScheme() === null && $uri->getHost() === null;
        }

        try {
            PublicHttpUrl::fromString($value);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
