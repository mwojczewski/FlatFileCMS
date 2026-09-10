<?php
$pageCount = count(array_filter($entries, static fn(array $entry): bool => !$entry['collection']));
$collectionCount = count($entries) - $pageCount;
$activeCount = count(array_filter($entries, static fn(array $entry): bool => $entry['enabled']));
$identities = array_fill_keys(array_map(static fn(array $entry): string => $entry['identity']->value(), $entries), true);
$hasChildren = static function (string $identity) use ($identities): bool {
    foreach ($identities as $candidate => $_) {
        if (str_starts_with($candidate, $identity . '/')) {
            return true;
        }
    }

    return false;
};
$pageIcon = static function (bool $collection, bool $homepage): string {
    if ($collection) {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-collection" viewBox="0 0 16 16"><path d="M2.5 3.5a.5.5 0 0 1 0-1h11a.5.5 0 0 1 0 1zm2-2a.5.5 0 0 1 0-1h7a.5.5 0 0 1 0 1zM0 13a1.5 1.5 0 0 0 1.5 1.5h13A1.5 1.5 0 0 0 16 13V6a1.5 1.5 0 0 0-1.5-1.5h-13A1.5 1.5 0 0 0 0 6zm1.5.5A.5.5 0 0 1 1 13V6a.5.5 0 0 1 .5-.5h13a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-.5.5z"/></svg>';
    }
    if ($homepage) {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-house" viewBox="0 0 16 16"><path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V8.207l.646.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293zM13 7.207V13.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V7.207l5-5z"/></svg>';
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-richtext" viewBox="0 0 16 16"><path d="M7 4.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0m-.861 1.542 1.33.886 1.854-1.855a.25.25 0 0 1 .289-.047l1.888.974V7.5a.5.5 0 0 1-.5.5H5a.5.5 0 0 1-.5-.5V7s1.54-1.274 1.639-1.208M5 9a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm0 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1z"/><path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1"/></svg>';
};
?>
<div class="pages-header editor-primary-actions">
    <div>
        <p class="eyebrow">Zawartość</p>
        <h2>Struktura witryny</h2>
        <p class="lead">Twórz strony, organizuj hierarchię i otwieraj edytor bloków.</p>
    </div>
    <a class="button" href="/admin/pages/create">
        <span aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-circle"
                viewBox="0 0 16 16">
                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16" />
                <path
                    d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4" />
            </svg>
        </span>Dodaj stronę
    </a>
</div>
<div class="pages-summary" aria-label="Podsumowanie zawartości">
    <div><strong><?= $pageCount ?></strong><span>Strony</span></div>
    <div><strong><?= $collectionCount ?></strong><span>Kolekcje</span></div>
    <div><strong><?= $activeCount ?></strong><span>Aktywne</span></div>
    <div><strong><?= (int) $languageCount ?></strong><span>Języki</span></div>
</div>
<div class="pages-tools">
    <label class="pages-search">
        <span class="sr-only">Szukaj strony</span>
        <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
            stroke-width="2">
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-4-4" />
        </svg>
        <input type="search" placeholder="Szukaj po nazwie lub ścieżce…" data-page-search autocomplete="off">
    </label>
    <span class="pages-result-count" data-page-result-count><?= count($entries) ?> pozycji</span>
</div>
<div class="pages-reorder-help"><span aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrows-expand"
            viewBox="0 0 16 16">
            <path fill-rule="evenodd"
                d="M1 8a.5.5 0 0 1 .5-.5h13a.5.5 0 0 1 0 1h-13A.5.5 0 0 1 1 8M7.646.146a.5.5 0 0 1 .708 0l2 2a.5.5 0 0 1-.708.708L8.5 1.707V5.5a.5.5 0 0 1-1 0V1.707L6.354 2.854a.5.5 0 1 1-.708-.708zM8 10a.5.5 0 0 1 .5.5v3.793l1.146-1.147a.5.5 0 0 1 .708.708l-2 2a.5.5 0 0 1-.708 0l-2-2a.5.5 0 0 1 .708-.708L7.5 14.293V10.5A.5.5 0 0 1 8 10" />
        </svg>
    </span>
    <p>
        <strong>Przeciągnij stronę, aby zmienić jej położenie.</strong> Upuść między wierszami, aby ustawić kolejność,
        albo na środku wiersza, aby utworzyć zagnieżdżenie.
    </p>
    <span data-page-tree-state role="status" aria-live="polite"></span>
</div>
<div class="table-wrap crud-table pages-table">
    <table>
        <thead>
            <tr>
                <th>Strona</th>
                <th>Typ</th>
                <th>Języki</th>
                <th>Stan</th>
                <th>Ostatnio edytowane</th>
                <th class="page-actions-column" aria-label="Akcje"></th>
            </tr>
        </thead>
        <tbody data-page-tree data-page-tree-csrf="<?= $escape($csrfToken) ?>">
            <?php if ($entries === []): ?>
                <tr>
                    <td class="table-empty" colspan="6">Brak stron. Dodaj pierwszą stronę, aby rozpocząć.</td>
                </tr><?php endif; ?>
            <?php foreach ($entries as $entry):
                $identity = $entry['identity']->value();
                $depth = count($entry['identity']->segments()) - 1;
                $branch = $hasChildren($identity); ?>
                <tr data-page-row data-page-identity="<?= $escape($identity) ?>" data-page-depth="<?= $depth ?>"
                    data-page-collection="<?= $entry['collection'] ? '1' : '0' ?>"
                    data-page-revision="<?= $escape($entry['revision']) ?>"
                    data-page-search-value="<?= $escape(mb_strtolower($entry['title'] . ' ' . $identity)) ?>"
                    <?= $entry['identity']->isHomepage() ? '' : ' draggable="true"' ?>>
                    <td class="page-cell">
                        <span class="tree" style="--depth:<?= $depth ?>">
                            <?php if ($branch): ?>
                                <button class="page-branch-toggle" type="button" data-page-branch-toggle aria-expanded="true"
                                    aria-label="Zwiń podstrony">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="m7 10 5 5 5-5" />
                                    </svg>
                                </button>
                            <?php else: ?>
                                <span class="page-branch-spacer"></span>
                            <?php endif; ?>
                            <span
                                class="page-type-icon <?= $entry['collection'] ? 'is-collection' : ($entry['identity']->isHomepage() ? 'is-homepage' : 'is-page') ?>"
                                aria-hidden="true"><?= $pageIcon($entry['collection'], $entry['identity']->isHomepage()) ?>
                            </span><?= $escape($entry['title']) ?></span>
                        <small style="--depth:<?= $depth ?>"><?= $escape($identity) ?></small>
                    </td>
                    <td>
                        <span class="page-kind"><?= $entry['collection'] ? 'Kolekcja' : 'Strona' ?></span>
                    </td>
                    <td>
                        <span class="language-badges">
                            <?php foreach ($languageCodes as $code): ?>
                                <span><?= $escape(strtoupper($code)) ?></span>
                            <?php endforeach; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($entry['enabled']): ?>
                            <span class="status on">
                                <i aria-hidden="true"></i>Aktywna
                            </span>
                        <?php else: ?>
                            <span class="status">
                                <i aria-hidden="true"></i>Wyłączona
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><time class="page-modified"
                            datetime="<?= $escape(date(DATE_ATOM, $entry['modifiedAt'])) ?>"><?= $escape(date('d.m.Y, H:i', $entry['modifiedAt'])) ?></time>
                    </td>
                    <td class="page-actions-column">
                        <details class="page-actions-menu" data-page-actions-menu>
                            <summary aria-label="Otwórz menu działań" title="Działania"><span aria-hidden="true">•••</span>
                            </summary>
                            <div class="page-actions-popover">
                                <?php if (!$entry['identity']->isHomepage()): ?>
                                    <a href="/admin/pages/create?parent=<?= rawurlencode($identity) ?>">
                                        <span aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                                class="bi bi-plus-circle" viewBox="0 0 16 16">
                                                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16" />
                                                <path
                                                    d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4" />
                                            </svg>
                                        </span>
                                        Dodaj podstronę
                                    </a>
                                <?php endif; ?>
                                <?php if (!$entry['collection']): ?>
                                    <a href="/admin/pages/builder?path=<?= rawurlencode($identity) ?>">
                                        <span aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                                class="bi bi-stack" viewBox="0 0 16 16">
                                                <path
                                                    d="m14.12 10.163 1.715.858c.22.11.22.424 0 .534L8.267 15.34a.6.6 0 0 1-.534 0L.165 11.555a.299.299 0 0 1 0-.534l1.716-.858 5.317 2.659c.505.252 1.1.252 1.604 0l5.317-2.66zM7.733.063a.6.6 0 0 1 .534 0l7.568 3.784a.3.3 0 0 1 0 .535L8.267 8.165a.6.6 0 0 1-.534 0L.165 4.382a.299.299 0 0 1 0-.535z" />
                                                <path
                                                    d="m14.12 6.576 1.715.858c.22.11.22.424 0 .534l-7.568 3.784a.6.6 0 0 1-.534 0L.165 7.968a.299.299 0 0 1 0-.534l1.716-.858 5.317 2.659c.505.252 1.1.252 1.604 0z" />
                                            </svg>
                                        </span>
                                        Otwórz edytor bloków
                                    </a>
                                <?php endif; ?>
                                <a href="/admin/media?path=<?= rawurlencode($identity) ?>">
                                    <span aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                            class="bi bi-image" viewBox="0 0 16 16">
                                            <path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0" />
                                            <path
                                                d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1z" />
                                        </svg>
                                    </span>
                                    Zarządzanie multimediami
                                </a>
                                <a
                                    href="<?= $entry['collection'] ? '/admin/collections/edit' : '/admin/pages/edit' ?>?path=<?= rawurlencode($identity) ?>">
                                    <span aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                            class="bi bi-pencil-square" viewBox="0 0 16 16">
                                            <path
                                                d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z" />
                                            <path fill-rule="evenodd"
                                                d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z" />
                                        </svg>
                                    </span>
                                    Edytuj ustawienia
                                </a>
                            </div>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr data-page-empty hidden>
                <td class="table-empty" colspan="6">Nie znaleziono strony pasującej do wyszukiwania.</td>
            </tr>
        </tbody>
    </table>
</div>