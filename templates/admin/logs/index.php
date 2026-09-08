<?php
$errorCount = $counts['ERROR'] + $counts['CRITICAL'] + $counts['ALERT'] + $counts['EMERGENCY'];
$warningCount = $counts['WARNING'];
$activeFilterCount = ($selectedLevel === '' ? 0 : 1) + ($search === '' ? 0 : 1);
?>
<div class="logs-header">
    <div><p class="eyebrow">Diagnostyka</p><h2>Stan aplikacji</h2><p class="lead">Najnowsze zdarzenia zapisane przez system i działania administratorów.</p></div>
    <span class="logs-readonly">Tylko do odczytu</span>
</div>
<section class="log-overview">
    <div class="log-health <?= $errorCount > 0 ? 'has-errors' : '' ?>"><span class="log-health-icon" aria-hidden="true"><?= $errorCount > 0 ? '!' : '✓' ?></span><span><strong><?= $errorCount > 0 ? 'Wymaga uwagi' : 'Brak błędów' ?></strong><small><?= $errorCount > 0 ? $errorCount . ' zdarzeń o wysokim priorytecie' : 'W wybranym zakresie nie wykryto błędów' ?></small></span></div>
    <div class="log-metric"><strong><?= number_format($result['total'], 0, ',', ' ') ?></strong><span>Pasujące zdarzenia</span></div>
    <div class="log-metric"><strong><?= $warningCount ?></strong><span>Ostrzeżenia</span></div>
    <div class="log-metric"><strong><?= count($files) ?></strong><span>Pliki logów</span></div>
</section>

<form class="panel log-filters" method="get" action="/admin/logs">
    <div class="log-filters-heading"><span aria-hidden="true">⌕</span><span><strong>Filtrowanie zdarzeń</strong><small><?= $activeFilterCount === 0 ? 'Wszystkie wpisy wybranego pliku' : 'Aktywne filtry: ' . $activeFilterCount ?></small></span></div>
    <label>
        Plik
        <select name="file">
            <?php foreach ($files as $file): ?>
                <option value="<?= $escape($file['name']) ?>" <?= $selected === $file['name'] ? ' selected' : '' ?>>
                    <?= $escape($file['name']) ?> · <?= number_format($file['size'] / 1024, 1, ',', ' ') ?> KB
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Poziom
        <select name="level">
            <option value="">Wszystkie poziomy</option>
            <?php foreach ($levels as $level): ?>
                <option value="<?= $level ?>" <?= $selectedLevel === $level ? ' selected' : '' ?>>
                    <?= $level ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="log-search">
        Szukaj
        <input type="search" name="q" value="<?= $escape($search) ?>" maxlength="200"
            placeholder="Komunikat, adres, kod błędu…">
    </label>
    <div class="log-filter-actions"><button class="button" type="submit">Zastosuj</button><a class="button secondary" href="/admin/logs">Wyczyść</a></div>
</form>

<?php if ($files === []): ?>
    <div class="empty-state">
        <h2>Brak logów</h2>
        <p>Aplikacja nie utworzyła jeszcze plików w <code>storage/logs</code>.</p>
    </div>
<?php elseif ($result['entries'] === []): ?>
    <div class="empty-state">
        <h2>Brak pasujących zdarzeń</h2>
        <p>Zmień poziom, wyszukiwaną frazę lub wybierz inny plik.</p>
    </div>
<?php else: ?>
    <div class="log-section-heading"><div><p class="eyebrow">Strumień zdarzeń</p><h2>Najnowsze wpisy</h2></div><span>maksymalnie 500 wyników</span></div>
    <div class="log-summary" aria-label="Podsumowanie poziomów">
        <?php foreach (['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL'] as $level): ?>
            <div><span
                    class="log-level level-<?= strtolower($level) ?>"><?= $level ?></span><strong><?= $counts[$level] ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if ($result['truncated']): ?>
        <p class="notice warning">Znaleziono więcej niż 500 zdarzeń. Pokazujemy 500 najnowszych — zawęź filtry, aby zobaczyć
            konkretny zakres.</p><?php endif; ?>
    <?php if ($result['malformed'] > 0): ?>
        <p class="notice warning">Pominięto <?= $result['malformed'] ?> wpisów, których nie udało się poprawnie odczytać.</p>
    <?php endif; ?>
    <div class="log-list">
        <?php foreach ($result['entries'] as $entry): ?>
            <article class="log-entry level-border-<?= strtolower($entry['level']) ?>">
                <header>
                    <span class="log-entry-icon" aria-hidden="true"><?= in_array($entry['level'], ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'], true) ? '!' : ($entry['level'] === 'WARNING' ? '△' : '·') ?></span>
                    <span class="log-level level-<?= strtolower($entry['level']) ?>">
                        <?= $escape($entry['level']) ?>
                    </span>
                    <time datetime="<?= $escape($entry['datetime']) ?>">
                        <?= $escape($entry['date']) ?>
                    </time>
                    <?php if ($entry['channel'] !== ''): ?>
                        <span class="log-channel">
                            <?= $escape($entry['channel']) ?>
                        </span>
                    <?php endif; ?>
                </header>
                <p class="log-message">
                    <?= $escape($entry['message']) ?>
                </p>
                <?php if ($entry['context'] !== [] || $entry['extra'] !== []): ?>
                    <details>
                        <summary>Dane techniczne zdarzenia</summary>
                        <dl class="log-context">
                            <?php foreach (['context' => $entry['context'], 'extra' => $entry['extra']] as $values):
                                foreach ($values as $key => $value): ?>
                                    <div>
                                        <dt><?= $escape((string) $key) ?></dt>
                                        <dd>
                                            <pre><?= $escape(is_scalar($value) || $value === null ? var_export($value, true) : (json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—')) ?></pre>
                                        </dd>
                                    </div>
                                <?php endforeach; endforeach; ?>
                        </dl>
                    </details>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
