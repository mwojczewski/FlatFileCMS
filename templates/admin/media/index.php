<?php
$imageCount = count(array_filter($items, static fn(array $entry): bool => $entry['item']->isImage()));
$fileCount = count($items) - $imageCount;
?>
<div class="library-header">
    <div>
        <p class="eyebrow">Zasoby strony</p>
        <h2>Biblioteka multimediów</h2>
        <p class="lead">Pliki przypisane do <code><?= $escape($identity->value()) ?></code>.</p>
    </div>
    <a class="button secondary" href="/admin/pages/builder?path=<?= rawurlencode($identity->value()) ?>">Wróć do
        bloków</a>
</div>
<div class="library-summary" aria-label="Podsumowanie biblioteki">
    <div><strong><?= count($items) ?></strong><span>Wszystkie pliki</span></div>
    <div><strong><?= $imageCount ?></strong><span>Obrazy</span></div>
    <div><strong><?= $fileCount ?></strong><span>Pozostałe</span></div>
</div>
<form class="media-upload" method="post" enctype="multipart/form-data" action="/admin/media/upload">
    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
    <input type="hidden" name="identity" value="<?= $escape($identity->value()) ?>">
    <label class="media-upload-field">
        <span class="media-upload-icon" aria-hidden="true">↑</span>
        <span>
            <strong>Dodaj plik do biblioteki</strong>
            <small>Wybierz obraz lub dokument z dysku.</small>
        </span>
        <input type="file" name="media" required></label>
    <div>
        <button type="submit">Prześlij plik</button>
        <small>Limity rozmiaru i typów ustawisz w konfiguracji multimediów.</small>
    </div>
</form>
<div class="library-tools">
    <label class="pages-search">
        <span class="sr-only">Szukaj pliku</span>
        <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
            stroke-width="2">
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-4-4" />
        </svg>
        <input type="search" placeholder="Szukaj po nazwie lub typie…" data-media-search autocomplete="off"></label>
    <span class="pages-result-count" data-media-result-count><?= count($items) ?> plików</span>
</div>
<div class="media-grid" data-media-grid>
    <?php if ($items === []): ?>
        <div class="empty-state media-empty">
            <strong>Brak multimediów tej strony.</strong>
            <p>Prześlij pierwszy plik. Zostanie zapisany bezpośrednio obok <code>content.yml</code>.</p>
        </div><?php endif; ?>
    <?php foreach ($items as $entry):
        $item = $entry['item']; ?>
        <article class="media-card" data-media-card
            data-media-search-value="<?= $escape(mb_strtolower($item->name()->value() . ' ' . $item->mimeType())) ?>">
            <div class="media-card-preview">
                <?php if ($item->isImage()): ?>
                    <img src="<?= $escape($entry['preview']) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <div class="media-file-icon">
                        <?= $escape(strtoupper(pathinfo($item->name()->value(), PATHINFO_EXTENSION))) ?>
                    </div>
                <?php endif; ?>
                <span class="media-kind"><?= $item->isImage() ? 'Obraz' : 'Plik' ?></span>
            </div>
            <div class="media-card-body">
                <strong title="<?= $escape($item->name()->value()) ?>"><?= $escape($item->name()->value()) ?></strong>
                <small><?= $escape($item->mimeType()) ?>
                    ·
                    <?= $escape($entry['size']) ?>
                    <?= $item->width() === null ? '' : " · {$item->width()}<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" fill=\"currentColor\" class=\"bi bi-x\" viewBox=\"0 0 16 16\"><path d=\"M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708\"/></svg>{$item->height()} px" ?></small>
                <div class="media-card-actions"><?php if ($item->isImage()): ?>
                        <button class="button compact secondary" type="button"
                            data-media-preview="<?= $escape($entry['url']) ?>"
                            data-media-preview-name="<?= $escape($item->name()->value()) ?>">Podgląd
                        </button>
                    <?php else: ?>
                        <a class="button compact secondary" href="<?= $escape($entry['url']) ?>" target="_blank"
                            rel="noopener">Otwórz</a>
                    <?php endif; ?>
                    <details class="page-actions-menu" data-actions-menu>
                        <summary aria-label="Otwórz menu działań" title="Działania">
                            <span aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                    class="bi bi-three-dots" viewBox="0 0 16 16">
                                    <path
                                        d="M3 9.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3m5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3m5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3" />
                                </svg>
                            </span>
                        </summary>
                        <div class="page-actions-popover">
                            <form method="post" action="/admin/media/delete"
                                data-confirm="Usunąć ten plik? Operacji nie można cofnąć.">
                                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="identity" value="<?= $escape($identity->value()) ?>">
                                <input type="hidden" name="name" value="<?= $escape($item->name()->value()) ?>">
                                <button type="submit" class="danger-menu-item">
                                    <span aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                            class="bi bi-x-circle" viewBox="0 0 16 16">
                                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16" />
                                            <path
                                                d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708" />
                                        </svg>
                                    </span>Usuń plik
                                </button>
                            </form>
                        </div>
                    </details>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
    <div class="empty-state media-empty" data-media-empty hidden><strong>Nie znaleziono pliku.</strong>
        <p>Zmień wyszukiwaną frazę.</p>
    </div>
</div>
<dialog class="media-lightbox" data-media-lightbox aria-label="Podgląd multimedia"><button type="button"
        class="media-lightbox-close" data-media-lightbox-close aria-label="Zamknij">×</button>
    <figure><img data-media-lightbox-image alt="">
        <figcaption data-media-lightbox-caption></figcaption>
    </figure>
</dialog>