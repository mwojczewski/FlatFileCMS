<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

use RuntimeException;

final class PrerenderNotFoundException extends RuntimeException
{
    public function __construct(string $locale, string $contentPath)
    {
        $route = '/' . $locale . ($contentPath === '' ? '/' : '/' . $contentPath);
        parent::__construct(\sprintf('React prerender for route "%s" is not available.', $route));
    }
}
