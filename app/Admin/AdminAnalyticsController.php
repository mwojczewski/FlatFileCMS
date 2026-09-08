<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Analytics\CloudflareAnalyticsService;
use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;

final readonly class AdminAnalyticsController
{
    public function __construct(
        private Authenticator $authenticator,
        private CloudflareAnalyticsService $analytics,
        private AdminView $views,
        private AdminLayout $layout,
    ) {
    }

    public function dashboard(Request $request): Response
    {
        if ($this->authenticator->user() === null) {
            return Response::redirect('/admin/login');
        }
        $range = $this->range($request);
        $data = $this->analytics->dashboard($range);

        return $this->layout->render(
            'Analityka',
            $this->views->render('dashboard', ['analytics' => $data]),
            active: 'dashboard',
        );
    }

    public function export(Request $request): Response
    {
        $this->requireUser();
        $range = $this->range($request);
        $data = $this->analytics->dashboard($range);
        if (($data['status'] ?? null) !== 'ready') {
            throw new HttpException(503, 'ANALYTICS_UNAVAILABLE', 'Dane analityczne nie są obecnie dostępne.');
        }

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new HttpException(500, 'CSV_EXPORT_FAILED', 'Nie udało się utworzyć eksportu.');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Sekcja', 'Nazwa', 'Odsłony', 'Wizyty'], ';', '"', '');
        foreach (['topPages' => 'Strony', 'countries' => 'Kraje', 'devices' => 'Urządzenia', 'browsers' => 'Przeglądarki', 'systems' => 'Systemy'] as $key => $section) {
            $rows = $data[$key] ?? [];
            if (!\is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                if (!\is_array($row)) {
                    continue;
                }
                fputcsv($stream, [
                    $section,
                    $this->csvValue($row['label'] ?? ''),
                    $this->csvValue($row['pageViews'] ?? 0),
                    $this->csvValue($row['visits'] ?? 0),
                ], ';', '"', '');
            }
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        if (!\is_string($csv)) {
            throw new HttpException(500, 'CSV_EXPORT_FAILED', 'Nie udało się utworzyć eksportu.');
        }

        return new Response($csv, headers: [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => \sprintf('attachment; filename="cloudflare-analytics-%s.csv"', $range),
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function range(Request $request): string
    {
        $range = $request->query()['range'] ?? '24h';
        return \is_string($range) && \in_array($range, ['24h', '7d', '30d', '90d'], true) ? $range : '24h';
    }

    private function csvValue(mixed $value): string
    {
        return \is_string($value) || \is_int($value) || \is_float($value) ? (string) $value : '';
    }

    private function requireUser(): void
    {
        try {
            $this->authenticator->requireUser();
        } catch (AuthenticationException $exception) {
            throw new HttpException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required.', previous: $exception);
        }
    }
}
