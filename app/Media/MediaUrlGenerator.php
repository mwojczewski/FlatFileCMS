<?php

declare(strict_types=1);

namespace FlatFileCms\Media;

use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Content\Slug;

final readonly class MediaUrlGenerator
{
    public function original(PageIdentity $identity, MediaItem $item): string
    {
        $segments = \array_map(
            static fn(Slug $segment): string => \rawurlencode($segment->value()),
            $identity->segments(),
        );
        $segments[] = $item->fingerprint();
        $segments[] = \rawurlencode($item->name()->value());

        return '/media/' . \implode('/', $segments);
    }

    public function variant(
        PageIdentity $identity,
        MediaItem $item,
        ?int $width = null,
        ?int $height = null,
        ?string $format = null,
        string $fit = 'contain',
    ): string {
        $query = [];
        if ($width !== null) {
            $query['w'] = (string) $width;
        }
        if ($height !== null) {
            $query['h'] = (string) $height;
        }
        if ($format !== null) {
            $query['format'] = $format;
        }
        if ($fit !== 'contain') {
            $query['fit'] = $fit;
        }

        $url = $this->original($identity, $item);

        return $query === [] ? $url : $url . '?' . \http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
