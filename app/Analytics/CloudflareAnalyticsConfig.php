<?php

declare(strict_types=1);

namespace FlatFileCms\Analytics;

use FlatFileCms\Core\Environment;

final readonly class CloudflareAnalyticsConfig
{
    public function __construct(
        public bool $enabled,
        public string $apiToken,
        public string $accountId,
        public string $zoneId,
        public string $siteTag,
        public string $hostname,
        public int $cacheTtl,
        public int $timeout,
        public string $timezone,
    ) {}

    public static function fromEnvironment(Environment $environment): self
    {
        return new self(
            $environment->boolean('CLOUDFLARE_ANALYTICS_ENABLED', false),
            trim($environment->get('CLOUDFLARE_API_TOKEN', '')),
            trim($environment->get('CLOUDFLARE_ACCOUNT_ID', '')),
            trim($environment->get('CLOUDFLARE_ZONE_ID', '')),
            trim($environment->get('CLOUDFLARE_WEB_ANALYTICS_SITE_TAG', '')),
            strtolower(trim($environment->get('CLOUDFLARE_ANALYTICS_HOSTNAME', ''))),
            $environment->integer('CLOUDFLARE_ANALYTICS_CACHE_TTL', 600, 60),
            $environment->integer('CLOUDFLARE_ANALYTICS_TIMEOUT', 10, 1),
            $environment->get('CLOUDFLARE_ANALYTICS_TIMEZONE', 'Europe/Warsaw'),
        );
    }

    /** @return list<string> */
    public function missing(): array
    {
        $missing = [];
        foreach ([
            'CLOUDFLARE_API_TOKEN' => $this->apiToken,
            'CLOUDFLARE_ACCOUNT_ID' => $this->accountId,
            'CLOUDFLARE_ZONE_ID' => $this->zoneId,
            'CLOUDFLARE_WEB_ANALYTICS_SITE_TAG' => $this->siteTag,
        ] as $name => $value) {
            if ($value === '') {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    public function configured(): bool
    {
        return $this->enabled && $this->missing() === [];
    }
}
