<?php

declare(strict_types=1);

namespace FlatFileCms\Http;

final readonly class PublicHtmlCacheEntry
{
    public function __construct(
        public string $html,
        public int $modifiedAt,
    ) {}
}
