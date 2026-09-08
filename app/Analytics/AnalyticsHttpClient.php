<?php

declare(strict_types=1);

namespace FlatFileCms\Analytics;

interface AnalyticsHttpClient
{
    /** @param array<string, mixed> $payload */
    public function postJson(string $url, array $payload, string $token, int $timeout): string;
}
