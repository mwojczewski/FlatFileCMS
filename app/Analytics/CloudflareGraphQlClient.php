<?php

declare(strict_types=1);

namespace FlatFileCms\Analytics;

use JsonException;

final readonly class CloudflareGraphQlClient
{
    private const string ENDPOINT = 'https://api.cloudflare.com/client/v4/graphql';

    public function __construct(
        private AnalyticsHttpClient $http,
        private CloudflareAnalyticsConfig $config,
    ) {}

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    public function query(string $query, array $variables): array
    {
        $response = $this->http->postJson(
            self::ENDPOINT,
            ['query' => $query, 'variables' => $variables],
            $this->config->apiToken,
            $this->config->timeout,
        );

        try {
            $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new AnalyticsException('Cloudflare API zwróciło nieprawidłową odpowiedź.', previous: $exception);
        }
        if (!\is_array($decoded)) {
            throw new AnalyticsException('Cloudflare API zwróciło nieprawidłową odpowiedź.');
        }
        $errors = $decoded['errors'] ?? null;
        if (\is_array($errors) && $errors !== []) {
            $first = $errors[0] ?? null;
            $message = \is_array($first) && \is_string($first['message'] ?? null)
                ? $first['message']
                : 'Zapytanie Cloudflare GraphQL nie powiodło się.';
            throw new AnalyticsException($message);
        }
        $data = $decoded['data'] ?? null;
        if (!\is_array($data)) {
            throw new AnalyticsException('Odpowiedź Cloudflare nie zawiera danych.');
        }

        $result = [];
        foreach ($data as $key => $value) {
            if (\is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
