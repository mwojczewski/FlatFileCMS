<?php

declare(strict_types=1);

namespace FlatFileCms\Analytics;

use JsonException;

final readonly class NativeAnalyticsHttpClient implements AnalyticsHttpClient
{
    /** @param array<string, mixed> $payload */
    public function postJson(string $url, array $payload, string $token, int $timeout): string
    {
        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new AnalyticsException('Nie udało się przygotować zapytania do Cloudflare.', previous: $exception);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Authorization: Bearer ' . $token,
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'User-Agent: FlatFileCMS-Cloudflare-Analytics/1.0',
                ],
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if (!\is_string($response)) {
            throw new AnalyticsException('Cloudflare Analytics jest chwilowo niedostępne.');
        }

        $status = $this->status($http_response_header);
        if ($status < 200 || $status >= 300) {
            throw new AnalyticsException(\sprintf('Cloudflare API zwróciło status HTTP %d.', $status));
        }

        return $response;
    }

    /** @param list<string> $headers */
    private function status(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }
}
