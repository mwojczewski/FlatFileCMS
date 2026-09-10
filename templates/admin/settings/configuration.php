<?php
$string = static fn(mixed $value): string => is_string($value) ? $value : '';
$mapping = static fn(mixed $value): array => is_array($value) && !array_is_list($value) ? $value : [];
$json = static fn(array $value): string => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
?>
<div class="settings-intro">
    <div>
        <p class="eyebrow">Ustawienia globalne</p>
        <h2>Konfiguracja witryny</h2>
        <p class="lead">Najważniejsze opcje publikacji, SEO i obsługi plików w jednym miejscu.</p>
    </div>
    <span class="settings-state"><i aria-hidden="true"></i>Konfiguracja aktywna</span>
</div>
<nav class="settings-submenu" aria-label="Sekcje konfiguracji">
    <a href="#site-settings">
        <span aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                viewBox="0 0 16 16">
                <path
                    d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V8.207l.646.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293zM13 7.207V13.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V7.207l5-5z" />
            </svg>
        </span>
        <span><strong>Witryna</strong>
            <small>Nazwa, adres i layout</small>
        </span>
    </a>
    <a href="#seo-settings">
        <span aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M6.5 4.482c1.664-1.673 5.825 1.254 0 5.018-5.825-3.764-1.664-6.69 0-5.018" />
                <path
                    d="M13 6.5a6.47 6.47 0 0 1-1.258 3.844q.06.044.115.098l3.85 3.85a1 1 0 0 1-1.414 1.415l-3.85-3.85a1 1 0 0 1-.1-.115h.002A6.5 6.5 0 1 1 13 6.5M6.5 12a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11" />
            </svg>
        </span>
        <span><strong>SEO</strong>
            <small>Metadane i języki</small>
        </span>
    </a>
    <a href="#media-settings">
        <span aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path
                    d="M2 3a.5.5 0 0 0 .5.5h11a.5.5 0 0 0 0-1h-11A.5.5 0 0 0 2 3m2-2a.5.5 0 0 0 .5.5h7a.5.5 0 0 0 0-1h-7A.5.5 0 0 0 4 1m2.765 5.576A.5.5 0 0 0 6 7v5a.5.5 0 0 0 .765.424l4-2.5a.5.5 0 0 0 0-.848z" />
                <path
                    d="M1.5 14.5A1.5 1.5 0 0 1 0 13V6a1.5 1.5 0 0 1 1.5-1.5h13A1.5 1.5 0 0 1 16 6v7a1.5 1.5 0 0 1-1.5 1.5zm13-1a.5.5 0 0 0 .5-.5V6a.5.5 0 0 0-.5-.5h-13A.5.5 0 0 0 1 6v7a.5.5 0 0 0 .5.5z" />
            </svg></span>
        <span><strong>Multimedia</strong>
            <small>Upload i obrazy</small>
        </span>
    </a>
    <a href="#text-files">
        <span aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path
                    d="M5.5 7a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1zM5 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5" />
                <path
                    d="M9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.5zm0 1v2A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1z" />
            </svg>
        </span>
        <span><strong>Pliki tekstowe</strong>
            <small>LLM i security.txt</small>
        </span>
    </a>
</nav>
<form class="stack crud-form" method="post" action="/admin/settings" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="revision"
        value="<?= $escape($document->revision()->value()) ?>">
    <?php require __DIR__ . '/sections/site.php'; ?>
    <?php require __DIR__ . '/sections/seo.php'; ?>
    <?php require __DIR__ . '/sections/media.php'; ?>
    <div class="actions form-actions"><button type="submit">Zapisz konfigurację witryny</button></div>
</form>
<?php require __DIR__ . '/sections/text-files.php'; ?>