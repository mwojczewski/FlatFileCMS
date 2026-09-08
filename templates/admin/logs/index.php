<section class="log-overview">
    <div>
        <p class="lead">
            Czytelny podgląd zdarzeń zapisanych przez aplikację. Najnowsze wpisy są wyświetlane jako
            pierwsze.
        </p>
        <p class="form-hint">
            Widok jest tylko do odczytu. Dla bezpieczeństwa pokazuje maksymalnie 500 pasujących
            zdarzeń.
        </p>
    </div>
    <div
        class="log-health <?= ($counts['ERROR'] + $counts['CRITICAL'] + $counts['ALERT'] + $counts['EMERGENCY']) > 0 ? 'has-errors' : '' ?>">
        <strong>
            <?= number_format($result['total'], 0, ',', ' ') ?>
        </strong>
        <span>
            pasujących zdarzeń
        </span>
    </div>
</section>

<form class="panel log-filters" method="get" action="/admin/logs">
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
    <button class="button" type="submit">
        Zastosuj filtry
    </button>
    <a class="button secondary" href="/admin/logs">
        Wyczyść
    </a>
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