<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Analytics;

use FlatFileCms\Analytics\AnalyticsCache;
use FlatFileCms\Analytics\AnalyticsException;
use FlatFileCms\Analytics\AnalyticsHttpClient;
use FlatFileCms\Analytics\CloudflareAnalyticsConfig;
use FlatFileCms\Analytics\CloudflareAnalyticsService;
use FlatFileCms\Analytics\CloudflareGraphQlClient;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(CloudflareGraphQlClient::class)]
#[CoversClass(CloudflareAnalyticsService::class)]
#[CoversClass(AnalyticsCache::class)]
final class CloudflareAnalyticsTest extends TestCase
{
    private TemporaryProject $project;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItBuildsDashboardFromCloudflareResponses(): void
    {
        $http = new class implements AnalyticsHttpClient {
            /** @var list<array<string, mixed>> */
            public array $payloads = [];

            public function postJson(string $url, array $payload, string $token, int $timeout): string
            {
                $this->payloads[] = $payload;
                if (\count($this->payloads) === 1) {
                    return json_encode(['data' => ['viewer' => ['accounts' => [[
                        'current' => [['count' => 120, 'sum' => ['visits' => 60]]],
                        'previous' => [['count' => 100, 'sum' => ['visits' => 50]]],
                        'series' => [['count' => 120, 'sum' => ['visits' => 60], 'dimensions' => ['date' => '2026-09-08']]],
                        'topPages' => [['count' => 80, 'sum' => ['visits' => 40], 'dimensions' => ['requestPath' => '/']]],
                        'countries' => [['count' => 70, 'sum' => ['visits' => 35], 'dimensions' => ['countryName' => 'PL']]],
                        'devices' => [], 'browsers' => [], 'systems' => [],
                    ]], 'zones' => [[
                        'totals' => [['count' => 200, 'sum' => ['edgeResponseBytes' => 1048576]]],
                        'cache' => [['count' => 150, 'dimensions' => ['cacheStatus' => 'HIT']]],
                    ]]]]], JSON_THROW_ON_ERROR);
                }

                return json_encode(['data' => ['viewer' => ['accounts' => [[
                    'vitals' => [['count' => 10, 'quantiles' => [
                        'largestContentfulPaintP75' => 2200,
                        'interactionToNextPaintP75' => 180,
                        'cumulativeLayoutShiftP75' => 0.08,
                    ]]],
                    'vitalsPages' => [],
                ]]]]], JSON_THROW_ON_ERROR);
            }
        };
        $config = new CloudflareAnalyticsConfig(true, 'secret', 'account', 'zone', 'site', '', 600, 10, 'Europe/Warsaw');
        $service = new CloudflareAnalyticsService(
            new CloudflareGraphQlClient($http, $config),
            $config,
            new AnalyticsCache($this->project->path()),
            new NullLogger(),
        );

        $result = $service->dashboard('7d');
        $summary = $this->map($result['summary'] ?? null);
        $topPages = $this->rows($result['topPages'] ?? null);
        $topPage = $topPages[0] ?? [];
        $vitals = $this->map($result['vitals'] ?? null);

        self::assertSame('ready', $result['status']);
        self::assertSame(120, $summary['pageViews']);
        self::assertSame(20.0, $summary['pageViewsChange']);
        self::assertSame('/', $topPage['label']);
        self::assertSame(75.0, $summary['cacheHitRatio']);
        self::assertSame('good', $vitals['lcpRating']);
        self::assertCount(2, $http->payloads);
    }

    public function testItReportsGraphQlErrorsWithoutLeakingToken(): void
    {
        $http = new class implements AnalyticsHttpClient {
            public function postJson(string $url, array $payload, string $token, int $timeout): string
            {
                return '{"errors":[{"message":"not authorized for that account"}]}';
            }
        };
        $config = new CloudflareAnalyticsConfig(true, 'top-secret', 'account', 'zone', 'site', '', 600, 10, 'UTC');
        $client = new CloudflareGraphQlClient($http, $config);

        $this->expectException(AnalyticsException::class);
        $this->expectExceptionMessage('not authorized for that account');
        $client->query('{ viewer { accounts { id } } }', []);
    }

    /** @return array<string, mixed> */
    private function map(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (\is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    private function rows(mixed $value): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $row) {
            if (\is_array($row)) {
                $result[] = $this->map($row);
            }
        }

        return $result;
    }
}
