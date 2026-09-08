<?php
$status = is_string($analytics['status'] ?? null) ? $analytics['status'] : 'error';
$range = is_string($analytics['range'] ?? null) ? $analytics['range'] : '7d';
$number = static fn(mixed $value): string => number_format(is_numeric($value) ? (float) $value : 0, 0, ',', ' ');
$bytes = static function (mixed $value): string {
    $size = is_numeric($value) ? (float) $value : 0;
    foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
        if ($size < 1024 || $unit === 'TB')
            return number_format($size, $unit === 'B' ? 0 : 1, ',', ' ') . ' ' . $unit;
        $size /= 1024;
    }
    return '0 B';
};
$change = static function (mixed $value) use ($escape): string {
    if (!is_numeric($value))
        return '<span class="metric-change neutral">brak porównania</span>';
    $numeric = (float) $value;
    $class = $numeric > 0 ? 'up' : ($numeric < 0 ? 'down' : 'neutral');
    return '<span class="metric-change ' . $class . '">' . $escape(($numeric > 0 ? '+' : '') . number_format($numeric, 1, ',', ' ') . '%') . '</span>';
};
$rating = static fn(string $value): string => match ($value) {
    'good' => 'Dobry', 'needs-work' => 'Do poprawy', default => 'Słaby',
};
$rows = static fn(string $key): array => is_array($analytics[$key] ?? null) ? $analytics[$key] : [];
$contentSummary = is_array($contentSummary ?? null) ? $contentSummary : [];
?>
<section class="dashboard-overview" aria-labelledby="dashboard-overview-title">
    <div class="dashboard-welcome">
        <div>
            <p class="eyebrow">Przegląd systemu</p>
            <h2 id="dashboard-overview-title">Witaj w FlatFile CMS</h2>
            <p>Treść, struktura witryny i najważniejsze akcje w jednym miejscu.</p>
        </div>
        <a class="button" href="/admin/pages/create">Dodaj stronę</a>
    </div>
    <div class="content-summary" aria-label="Stan zawartości">
        <a href="/admin/pages"><strong><?= $number($contentSummary['pages'] ?? 0) ?></strong><span>Strony</span></a>
        <a href="/admin/pages"><strong><?= $number($contentSummary['collections'] ?? 0) ?></strong><span>Kolekcje</span></a>
        <a href="/admin/pages"><strong><?= $number($contentSummary['published'] ?? 0) ?></strong><span>Aktywne</span></a>
        <a href="/admin/settings"><strong><?= $number($contentSummary['languages'] ?? 0) ?></strong><span>Języki</span></a>
    </div>
    <nav class="dashboard-quick-actions" aria-label="Szybkie akcje">
        <a href="/admin/pages"><span class="quick-action-icon" aria-hidden="true">▤</span><span><strong>Zarządzaj stronami</strong><small>Treść i edytor bloków</small></span><b aria-hidden="true">→</b></a>
        <a href="/admin/navigation"><span class="quick-action-icon" aria-hidden="true">⌘</span><span><strong>Edytuj nawigację</strong><small>Menu i hierarchia linków</small></span><b aria-hidden="true">→</b></a>
        <a href="/admin/settings"><span class="quick-action-icon" aria-hidden="true">⚙</span><span><strong>Konfiguracja</strong><small>SEO, witryna i multimedia</small></span><b aria-hidden="true">→</b></a>
    </nav>
</section>

<div class="dashboard-section-heading">
    <div><p class="eyebrow">Analityka</p><h2>Ruch w witrynie</h2></div>
    <p>Dane z Cloudflare Web Analytics</p>
</div>
<div class="analytics-toolbar">
    <div>
        <p class="eyebrow">Cloudflare Web Analytics</p>
        <p class="lead">Ruch i jakość działania witryny z perspektywy odwiedzających.</p>
    </div>
    <div class="analytics-actions">
        <nav class="range-picker" aria-label="Zakres danych">
            <?php foreach (['24h' => '24 godz.', '7d' => '7 dni', '30d' => '30 dni', '90d' => '90 dni'] as $value => $label): ?>
                <a href="/admin?range=<?= $value ?>" class="<?= $range === $value ? 'active' : '' ?>" <?= $range === $value ? ' aria-current="page"' : '' ?>><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($status === 'ready'): ?><a class="button secondary"
                href="/admin/analytics/export?range=<?= $range ?>">Eksport CSV</a><?php endif; ?>
    </div>
</div>

<?php if ($status !== 'ready'): ?>
    <section class="analytics-empty">
        <div class="analytics-empty-icon" aria-hidden="true">⌁</div>
        <?php if ($status === 'disabled'): ?>
            <h2>Analityka jest wyłączona</h2>
            <p>Włącz ją ustawiając <code>CLOUDFLARE_ANALYTICS_ENABLED=1</code> w <code>.env.local</code>.</p>
        <?php elseif ($status === 'unconfigured'): ?>
            <h2>Uzupełnij konfigurację Cloudflare</h2>
            <p>Brakuje następujących wartości w <code>.env.local</code>:</p>
            <ul><?php foreach (($analytics['missing'] ?? []) as $name): ?>
                    <li><code><?= $escape((string) $name) ?></code></li><?php endforeach; ?>
            </ul>
        <?php else: ?>
            <h2>Nie udało się pobrać analityki</h2>
            <p><?= $escape((string) ($analytics['error'] ?? 'Spróbuj ponownie później.')) ?></p>
            <a class="button" href="/admin?range=<?= $range ?>">Spróbuj ponownie</a>
        <?php endif; ?>
    </section>
<?php else:
    $summary = is_array($analytics['summary'] ?? null) ? $analytics['summary'] : [];
    $series = $rows('series');
    $maxViews = max([1, ...array_map(static fn(array $row): int => (int) ($row['pageViews'] ?? 0), $series)]);
    $points = [];
    $visitPoints = [];
    $count = count($series);
    foreach ($series as $index => $point) {
        $x = $count > 1 ? 20 + ($index / ($count - 1)) * 760 : 400;
        $points[] = round($x, 1) . ',' . round(210 - ((int) ($point['pageViews'] ?? 0) / $maxViews) * 170, 1);
        $visitPoints[] = round($x, 1) . ',' . round(210 - ((int) ($point['visits'] ?? 0) / $maxViews) * 170, 1);
    }
    ?>
    <?php if (is_string($analytics['warning'] ?? null)): ?>
        <div class="analytics-notice warning"><?= $escape($analytics['warning']) ?></div><?php endif; ?>
    <section class="metric-grid" aria-label="Podsumowanie ruchu">
        <article class="metric-card">
            <span>Odsłony</span><strong><?= $number($summary['pageViews'] ?? 0) ?></strong><?= $change($summary['pageViewsChange'] ?? null) ?><small>względem
                poprzedniego okresu</small>
        </article>
        <article class="metric-card">
            <span>Wizyty</span><strong><?= $number($summary['visits'] ?? 0) ?></strong><?= $change($summary['visitsChange'] ?? null) ?><small>wejścia
                zewnętrzne i bezpośrednie</small>
        </article>
        <article class="metric-card"><span>Odsłon na
                wizytę</span><strong><?= $escape(number_format((float) ($summary['viewsPerVisit'] ?? 0), 2, ',', ' ')) ?></strong><span
                class="metric-change neutral">średnia</span><small>za wybrany okres</small></article>
        <article class="metric-card"><span>Żądania
                HTTP</span><strong><?= $number($summary['requests'] ?? 0) ?></strong><span
                class="metric-change neutral">edge</span><small>ruch użytkowników przez Cloudflare</small></article>
        <article class="metric-card">
            <span>Transfer</span><strong><?= $escape($bytes($summary['transferBytes'] ?? 0)) ?></strong><span
                class="metric-change neutral">edge</span><small>dane wysłane do odwiedzających</small>
        </article>
        <article class="metric-card"><span>Cache hit
                ratio</span><strong><?= $escape(number_format((float) ($summary['cacheHitRatio'] ?? 0), 1, ',', ' ')) ?>%</strong><span
                class="metric-change good">Cloudflare</span><small><?= ($analytics['cache'] ?? '') === 'stale' ? 'dane archiwalne' : 'dane odświeżone' ?></small>
        </article>
    </section>

    <section class="analytics-card traffic-chart">
        <header>
            <div>
                <p class="eyebrow">Ruch w czasie</p>
                <h2>Odsłony i wizyty</h2>
            </div>
            <div class="chart-legend"><span class="views">Odsłony</span><span class="visits">Wizyty</span></div>
        </header>
        <?php if ($series === []): ?>
            <p class="analytics-no-data">Brak danych w wybranym okresie.</p>
        <?php else: ?>
            <div class="line-chart">
                <svg viewBox="0 0 800 230" role="img" aria-label="Wykres odsłon i wizyt w czasie" preserveAspectRatio="none">
                    <g class="chart-grid">
                        <line x1="20" y1="40" x2="780" y2="40" />
                        <line x1="20" y1="125" x2="780" y2="125" />
                        <line x1="20" y1="210" x2="780" y2="210" />
                    </g>
                    <polyline class="chart-line visits" points="<?= $escape(implode(' ', $visitPoints)) ?>" />
                    <polyline class="chart-line views" points="<?= $escape(implode(' ', $points)) ?>" />
                </svg>
                <div class="chart-labels">
                    <?php foreach ($series as $index => $point):
                        if ($index % max(1, (int) ceil($count / 7)) !== 0 && $index !== $count - 1)
                            continue; ?><span><?= $escape((string) ($point['label'] ?? '')) ?></span><?php endforeach; ?>
                </div>
            </div><?php endif; ?>
    </section>

    <div class="analytics-columns">
        <?php foreach ([['topPages', 'Najpopularniejsze strony'], ['countries', 'Kraje odwiedzających']] as [$key, $title]):
            $ranking = $rows($key);
            $maximum = max([1, ...array_map(static fn(array $row): int => (int) ($row['pageViews'] ?? 0), $ranking)]); ?>
            <section class="analytics-card ranking-card">
                <header>
                    <div>
                        <p class="eyebrow">Ranking</p>
                        <h2><?= $title ?></h2>
                    </div><span>Odsłony</span>
                </header>
                <?php if ($ranking === []): ?>
                    <p class="analytics-no-data">Brak danych.</p><?php else: ?>
                    <ol class="bar-ranking">
                        <?php foreach ($ranking as $row):
                            $width = min(100, ((int) ($row['pageViews'] ?? 0) / $maximum) * 100); ?>
                            <li>
                                <div><span class="ranking-label"
                                        title="<?= $escape((string) ($row['label'] ?? '')) ?>"><?= $escape((string) ($row['label'] ?? '')) ?></span><strong><?= $number($row['pageViews'] ?? 0) ?></strong>
                                </div><span class="bar"><i style="width:<?= $escape(number_format($width, 1, '.', '')) ?>%"></i></span>
                            </li>
                        <?php endforeach; ?>
                    </ol><?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <section class="analytics-card">
        <header>
            <div>
                <p class="eyebrow">Real User Monitoring · P75</p>
                <h2>Core Web Vitals</h2>
            </div><span>75. percentyl</span>
        </header>
        <?php $vitals = is_array($analytics['vitals'] ?? null) ? $analytics['vitals'] : null;
        if ($vitals === null): ?>
            <p class="analytics-no-data">Brak danych Web Vitals. Sprawdź beacon Web Analytics i uprawnienia tokenu.</p>
        <?php else: ?>
            <div class="vitals-grid">
                <?php foreach ([['lcp', 'LCP', 'ms', 'Największy element'], ['inp', 'INP', 'ms', 'Reakcja na interakcję'], ['cls', 'CLS', '', 'Stabilność układu']] as [$key, $label, $unit, $description]):
                    $state = (string) ($vitals[$key . 'Rating'] ?? 'poor'); ?>
                    <article class="vital-card <?= $state ?>">
                        <div class="vital-gauge">
                            <span></span><strong><?= $escape($key === 'cls' ? number_format((float) ($vitals[$key] ?? 0), 3, ',', '') : $number($vitals[$key] ?? 0)) ?><small><?= $unit ?></small></strong>
                        </div>
                        <div>
                            <h3><?= $label ?></h3>
                            <p><?= $description ?></p><span class="vital-rating"><?= $rating($state) ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div><?php endif; ?>
    </section>

    <div class="analytics-columns analytics-breakdowns">
        <?php foreach ([['devices', 'Urządzenia'], ['browsers', 'Przeglądarki'], ['systems', 'Systemy operacyjne']] as [$key, $title]):
            $ranking = $rows($key);
            $total = max(1, array_sum(array_map(static fn(array $row): int => (int) ($row['pageViews'] ?? 0), $ranking))); ?>
            <section class="analytics-card compact-ranking">
                <header>
                    <h2><?= $title ?></h2>
                </header>
                <?php if ($ranking === []): ?>
                    <p class="analytics-no-data">Brak danych.</p><?php else: ?>
                    <ul>
                        <?php foreach ($ranking as $row): ?>
                            <li><span><?= $escape((string) ($row['label'] ?? '')) ?></span><span class="compact-bar"><i
                                        style="width:<?= $escape(number_format(((int) ($row['pageViews'] ?? 0) / $total) * 100, 1, '.', '')) ?>%"></i></span><strong><?= $escape(number_format(((int) ($row['pageViews'] ?? 0) / $total) * 100, 1, ',', '')) ?>%</strong>
                            </li><?php endforeach; ?>
                    </ul><?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
