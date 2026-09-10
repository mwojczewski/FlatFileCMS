<?php

declare(strict_types=1);

namespace FlatFileCms\Analytics;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class CloudflareAnalyticsService
{
    private const array RANGES = ['24h' => 1, '7d' => 7, '30d' => 30, '90d' => 90];

    public function __construct(
        private CloudflareGraphQlClient $client,
        private CloudflareAnalyticsConfig $config,
        private AnalyticsCache $cache,
        private LoggerInterface $logger,
    ) {}

    /** @return array<string, mixed> */
    public function dashboard(string $requestedRange): array
    {
        $range = \array_key_exists($requestedRange, self::RANGES) ? $requestedRange : '7d';
        if (!$this->config->enabled) {
            return $this->emptyResult($range, 'disabled');
        }
        if ($this->config->missing() !== []) {
            return $this->emptyResult($range, 'unconfigured');
        }

        $cacheKey = "cloudflare-{$range}";
        $cached = $this->cache->read($cacheKey, $this->config->cacheTtl);
        if ($cached !== null) {
            $cached['cache'] = 'fresh';
            return $cached;
        }

        $stale = $this->cache->readStale($cacheKey);
        if ($stale !== null) {
            $stale['cache'] = 'stale';
            $stale['warning'] = 'Dane z Cloudflare są chwilowo niedostępne. Pokazujemy ostatnią zapisane dane z cache.';
            return $stale;
        }

        $result = $this->emptyResult($range, 'empty');
        $result['warning'] = 'Brak danych w cache. Uruchom cron z komendy php bin/cms cloudflare:analytics:refresh.';

        return $result;
    }

    /** @return array<string, mixed> */
    public function refresh(string $requestedRange, bool $allowCacheFallback = false): array
    {
        $range = \array_key_exists($requestedRange, self::RANGES) ? $requestedRange : '7d';
        if (!$this->config->enabled) {
            return $this->emptyResult($range, 'disabled');
        }
        if ($this->config->missing() !== []) {
            return $this->emptyResult($range, 'unconfigured');
        }

        $cacheKey = "cloudflare-{$range}";
        try {
            $result = $this->fetch($range);
            $result['cache'] = 'fresh';
            $this->cache->write($cacheKey, $result);
            return $result;
        } catch (Throwable $exception) {
            $this->logger->warning('Cloudflare analytics request failed', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'range' => $range,
            ]);
            if ($allowCacheFallback) {
                $stale = $this->cache->readStale($cacheKey);
                if ($stale !== null) {
                    $stale['cache'] = 'stale';
                    $stale['warning'] = 'Nie udało się odświeżyć danych. Pokazujemy ostatnią zapisaną wersję.';
                    return $stale;
                }
            }

            $result = $this->emptyResult($range, 'error');
            $result['error'] = $this->friendlyError($exception);
            return $result;
        }
    }

    /** @return array<string, mixed> */
    private function fetch(string $range): array
    {
        $days = self::RANGES[$range];
        $zone = new DateTimeZone($this->config->timezone);
        $end = new DateTimeImmutable('now', $zone);
        $start = $end->modify(\sprintf('-%d days', $days));
        $previousStart = $start->modify(\sprintf('-%d days', $days));
        $hourly = $range === '24h';
        $dimension = $hourly ? 'datetimeHour' : 'date';
        $variables = [
            'accountTag' => $this->config->accountId,
            'zoneTag' => $this->config->zoneId,
            'siteTag' => $this->config->siteTag,
            'start' => $start->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
            'end' => $end->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
            'previousStart' => $previousStart->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
        ];
        if ($this->config->hostname !== '') {
            $variables['hostname'] = $this->config->hostname;
        }
        $traffic = $this->client->query($this->trafficQuery($dimension), $variables);
        $account = $this->account($traffic);
        if ($account === null) {
            throw new AnalyticsException('Cloudflare nie zwróciło danych dla wskazanego konta.');
        }

        $result = [
            'status' => 'ready',
            'range' => $range,
            'generatedAt' => $end->format(DATE_ATOM),
            'cache' => 'miss',
            'warning' => null,
            'error' => null,
            'summary' => [...$this->summary($account), ...$this->zoneSummary($traffic)],
            'series' => $this->series($this->rows($account, 'series'), $dimension, $hourly, $zone),
            'topPages' => $this->ranking($this->rows($account, 'topPages'), 'requestPath'),
            'countries' => $this->ranking($this->rows($account, 'countries'), 'countryName'),
            'devices' => $this->ranking($this->rows($account, 'devices'), 'deviceType'),
            'browsers' => $this->ranking($this->rows($account, 'browsers'), 'userAgentBrowser'),
            'systems' => $this->ranking($this->rows($account, 'systems'), 'userAgentOS'),
            'vitals' => null,
            'vitalsPages' => [],
        ];

        try {
            $vitalsVariables = [
                'accountTag' => $this->config->accountId,
                'siteTag' => $this->config->siteTag,
                'start' => $start->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
                'end' => $end->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
            ];
            if ($this->config->hostname !== '') {
                $vitalsVariables['hostname'] = $this->config->hostname;
            }
            $vitals = $this->client->query($this->vitalsQuery(), $vitalsVariables);
            $vitalsAccount = $this->account($vitals);
            if ($vitalsAccount !== null) {
                $vitalRows = $this->rows($vitalsAccount, 'vitals');
                $result['vitals'] = $this->vitals($vitalRows[0] ?? []);
                $result['vitalsPages'] = $this->vitalsPages($this->rows($vitalsAccount, 'vitalsPages'));
            }
        } catch (Throwable $exception) {
            $result['warning'] = 'Ruch został pobrany, ale Cloudflare nie udostępniło danych Core Web Vitals.';
            $this->logger->notice('Cloudflare Web Vitals query failed', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        return $result;
    }

    private function trafficQuery(string $timeDimension): string
    {
        $host = $this->config->hostname === '' ? '' : ', requestHost: $hostname';
        $hostnameVariable = $this->config->hostname === '' ? '' : ', $hostname: string!';
        $baseFilter = 'siteTag: $siteTag, datetime_geq: $start, datetime_lt: $end' . $host;
        $previousFilter = 'siteTag: $siteTag, datetime_geq: $previousStart, datetime_lt: $start' . $host;

        return <<<GRAPHQL
query Dashboard(\$accountTag: string!, \$zoneTag: string!, \$siteTag: string!, \$start: Time!, \$end: Time!, \$previousStart: Time!{$hostnameVariable}) {
  viewer {
    accounts(filter: {accountTag: \$accountTag}) {
      current: rumPageloadEventsAdaptiveGroups(limit: 1, filter: {{$baseFilter}}) { count sum { visits } }
      previous: rumPageloadEventsAdaptiveGroups(limit: 1, filter: {{$previousFilter}}) { count sum { visits } }
      series: rumPageloadEventsAdaptiveGroups(limit: 500, orderBy: [{$timeDimension}_ASC], filter: {{$baseFilter}}) {
        count sum { visits } dimensions { {$timeDimension} }
      }
      topPages: rumPageloadEventsAdaptiveGroups(limit: 12, orderBy: [count_DESC], filter: {{$baseFilter}}) {
        count sum { visits } dimensions { requestPath }
      }
      countries: rumPageloadEventsAdaptiveGroups(limit: 12, orderBy: [count_DESC], filter: {{$baseFilter}}) {
        count sum { visits } dimensions { countryName }
      }
      devices: rumPageloadEventsAdaptiveGroups(limit: 8, orderBy: [count_DESC], filter: {{$baseFilter}}) {
        count sum { visits } dimensions { deviceType }
      }
      browsers: rumPageloadEventsAdaptiveGroups(limit: 8, orderBy: [count_DESC], filter: {{$baseFilter}}) {
        count sum { visits } dimensions { userAgentBrowser }
      }
      systems: rumPageloadEventsAdaptiveGroups(limit: 8, orderBy: [count_DESC], filter: {{$baseFilter}}) {
        count sum { visits } dimensions { userAgentOS }
      }
    }
    zones(filter: {zoneTag: \$zoneTag}) {
      totals: httpRequestsAdaptiveGroups(limit: 1, filter: {datetime_geq: \$start, datetime_lt: \$end, requestSource: "eyeball"}) {
        count sum { edgeResponseBytes }
      }
      cache: httpRequestsAdaptiveGroups(limit: 20, orderBy: [count_DESC], filter: {datetime_geq: \$start, datetime_lt: \$end, requestSource: "eyeball"}) {
        count dimensions { cacheStatus }
      }
    }
  }
}
GRAPHQL;
    }

    private function vitalsQuery(): string
    {
        $host = $this->config->hostname === '' ? '' : ', requestHost: $hostname';
        $hostnameVariable = $this->config->hostname === '' ? '' : ', $hostname: string!';
        $filter = 'siteTag: $siteTag, datetime_geq: $start, datetime_lt: $end' . $host;

        return <<<GRAPHQL
query Vitals(\$accountTag: string!, \$siteTag: string!, \$start: Time!, \$end: Time!{$hostnameVariable}) {
  viewer {
    accounts(filter: {accountTag: \$accountTag}) {
      vitals: rumWebVitalsEventsAdaptiveGroups(limit: 1, filter: {{$filter}}) {
        count
        quantiles { largestContentfulPaintP75 interactionToNextPaintP75 cumulativeLayoutShiftP75 }
      }
      vitalsPages: rumWebVitalsEventsAdaptiveGroups(limit: 8, orderBy: [count_DESC], filter: {{$filter}}) {
        count dimensions { requestPath }
        quantiles { largestContentfulPaintP75 interactionToNextPaintP75 cumulativeLayoutShiftP75 }
      }
    }
  }
}
GRAPHQL;
    }

    /**
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    private function summary(array $account): array
    {
        $current = $this->rows($account, 'current')[0] ?? [];
        $previous = $this->rows($account, 'previous')[0] ?? [];
        $currentSum = $this->map($current['sum'] ?? null);
        $previousSum = $this->map($previous['sum'] ?? null);
        $views = $this->number($current['count'] ?? 0);
        $visits = $this->number($currentSum['visits'] ?? 0);
        $previousViews = $this->number($previous['count'] ?? 0);
        $previousVisits = $this->number($previousSum['visits'] ?? 0);

        return [
            'pageViews' => $views,
            'visits' => $visits,
            'viewsPerVisit' => $visits > 0 ? round($views / $visits, 2) : 0.0,
            'pageViewsChange' => $this->change($views, $previousViews),
            'visitsChange' => $this->change($visits, $previousVisits),
        ];
    }

    /**
     * @param array<string, mixed> $traffic
     * @return array<string, int|float>
     */
    private function zoneSummary(array $traffic): array
    {
        $viewer = $this->map($traffic['viewer'] ?? null);
        $zones = $viewer['zones'] ?? null;
        if (!\is_array($zones) || !array_is_list($zones)) {
            return ['requests' => 0, 'transferBytes' => 0, 'cacheHitRatio' => 0.0];
        }
        $zone = $this->map($zones[0] ?? null);
        $totals = $this->rows($zone, 'totals')[0] ?? [];
        $sum = $this->map($totals['sum'] ?? null);
        $requests = $this->number($totals['count'] ?? 0);
        $cached = 0;
        foreach ($this->rows($zone, 'cache') as $row) {
            $dimensions = $this->map($row['dimensions'] ?? null);
            $cacheStatus = strtoupper(\is_string($dimensions['cacheStatus'] ?? null) ? $dimensions['cacheStatus'] : '');
            if (\in_array($cacheStatus, ['HIT', 'REVALIDATED', 'UPDATING'], true)) {
                $cached += $this->number($row['count'] ?? 0);
            }
        }

        return [
            'requests' => $requests,
            'transferBytes' => $this->number($sum['edgeResponseBytes'] ?? 0),
            'cacheHitRatio' => $requests > 0 ? round(($cached / $requests) * 100, 1) : 0.0,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function series(array $rows, string $dimension, bool $hourly, DateTimeZone $zone): array
    {
        $result = [];
        foreach ($rows as $row) {
            $dimensions = $this->map($row['dimensions'] ?? null);
            $raw = \is_string($dimensions[$dimension] ?? null) ? $dimensions[$dimension] : '';
            if ($raw === '') {
                continue;
            }
            $date = new DateTimeImmutable($raw);
            $sum = $this->map($row['sum'] ?? null);
            $result[] = [
                'label' => $date->setTimezone($zone)->format($hourly ? 'H:i' : 'd.m'),
                'pageViews' => $this->number($row['count'] ?? 0),
                'visits' => $this->number($sum['visits'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function ranking(array $rows, string $dimension): array
    {
        $result = [];
        foreach ($rows as $row) {
            $dimensions = $this->map($row['dimensions'] ?? null);
            $label = \is_string($dimensions[$dimension] ?? null) ? trim($dimensions[$dimension]) : '';
            if ($label === '') {
                $label = 'Nieznane';
            }
            $sum = $this->map($row['sum'] ?? null);
            $result[] = [
                'label' => $label,
                'pageViews' => $this->number($row['count'] ?? 0),
                'visits' => $this->number($sum['visits'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function vitals(array $row): ?array
    {
        $quantiles = $this->map($row['quantiles'] ?? null);
        if ($quantiles === []) {
            return null;
        }

        return $this->vitalValues($quantiles);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function vitalsPages(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $dimensions = $this->map($row['dimensions'] ?? null);
            $quantiles = $this->map($row['quantiles'] ?? null);
            $result[] = [
                'path' => \is_string($dimensions['requestPath'] ?? null) ? $dimensions['requestPath'] : 'Nieznane',
                ...$this->vitalValues($quantiles),
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $quantiles
     * @return array<string, mixed>
     */
    private function vitalValues(array $quantiles): array
    {
        $lcp = $this->float($quantiles['largestContentfulPaintP75'] ?? 0);
        $inp = $this->float($quantiles['interactionToNextPaintP75'] ?? 0);
        $cls = $this->float($quantiles['cumulativeLayoutShiftP75'] ?? 0);

        return [
            'lcp' => $lcp,
            'lcpRating' => $lcp <= 2500 ? 'good' : ($lcp <= 4000 ? 'needs-work' : 'poor'),
            'inp' => $inp,
            'inpRating' => $inp <= 200 ? 'good' : ($inp <= 500 ? 'needs-work' : 'poor'),
            'cls' => $cls,
            'clsRating' => $cls <= 0.1 ? 'good' : ($cls <= 0.25 ? 'needs-work' : 'poor'),
        ];
    }

    /**
     * @param array<string, mixed> $account
     * @return list<array<string, mixed>>
     */
    private function rows(array $account, string $key): array
    {
        $rows = $account[$key] ?? [];
        if (!\is_array($rows) || !array_is_list($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $mapped = $this->map($row);
            if ($mapped !== []) {
                $result[] = $mapped;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function account(array $data): ?array
    {
        $viewer = $this->map($data['viewer'] ?? null);
        $accounts = $viewer['accounts'] ?? null;
        if (!\is_array($accounts) || !array_is_list($accounts)) {
            return null;
        }

        $account = $this->map($accounts[0] ?? null);
        return $account === [] ? null : $account;
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

    private function number(mixed $value): int
    {
        return \is_int($value) || \is_float($value) ? (int) round($value) : 0;
    }

    private function float(mixed $value): float
    {
        return \is_int($value) || \is_float($value) ? round((float) $value, 3) : 0.0;
    }

    private function change(int $current, int $previous): ?float
    {
        return $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : null;
    }

    /** @return array<string, mixed> */
    private function emptyResult(string $range, string $status): array
    {
        return [
            'status' => $status,
            'range' => $range,
            'missing' => $this->config->missing(),
            'summary' => ['pageViews' => 0, 'visits' => 0, 'viewsPerVisit' => 0, 'pageViewsChange' => null, 'visitsChange' => null, 'requests' => 0, 'transferBytes' => 0, 'cacheHitRatio' => 0],
            'series' => [],
            'topPages' => [],
            'countries' => [],
            'devices' => [],
            'browsers' => [],
            'systems' => [],
            'vitals' => null,
            'vitalsPages' => [],
            'warning' => null,
            'error' => null,
        ];
    }

    private function friendlyError(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'auth') || str_contains($message, 'permission') || str_contains($message, 'not authorized')) {
            return 'Cloudflare odrzuciło token lub token nie ma uprawnienia Analytics: Read.';
        }

        return 'Nie udało się pobrać danych z Cloudflare. Szczegóły zapisano w logu aplikacji.';
    }
}
