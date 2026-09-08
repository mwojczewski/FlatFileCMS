<?php
$string = static fn(mixed $value): string => is_string($value) ? $value : '';
$mapping = static fn(mixed $value): array => is_array($value) && !array_is_list($value) ? $value : [];
$json = static fn(array $value): string => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
?>
<div class="settings-intro">
    <div><p class="eyebrow">Ustawienia globalne</p><h2>Konfiguracja witryny</h2><p class="lead">Najważniejsze opcje publikacji, SEO i obsługi plików w jednym miejscu.</p></div>
    <span class="settings-state"><i aria-hidden="true"></i>Konfiguracja aktywna</span>
</div>
<nav class="settings-submenu" aria-label="Sekcje konfiguracji">
    <a href="#site-settings"><span aria-hidden="true">⌂</span><span><strong>Witryna</strong><small>Nazwa, adres i layout</small></span></a>
    <a href="#seo-settings"><span aria-hidden="true">◎</span><span><strong>SEO</strong><small>Metadane i języki</small></span></a>
    <a href="#media-settings"><span aria-hidden="true">▧</span><span><strong>Multimedia</strong><small>Upload i obrazy</small></span></a>
    <a href="#text-files"><span aria-hidden="true">≡</span><span><strong>Pliki tekstowe</strong><small>LLM i security.txt</small></span></a>
</nav>
<form class="stack crud-form" method="post" action="/admin/settings">
    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="revision"
        value="<?= $escape($document->revision()->value()) ?>">
    <?php require __DIR__ . '/sections/site.php'; ?>
    <?php require __DIR__ . '/sections/seo.php'; ?>
    <?php require __DIR__ . '/sections/media.php'; ?>
    <div class="actions form-actions"><button type="submit">Zapisz konfigurację witryny</button></div>
</form>
<?php require __DIR__ . '/sections/text-files.php'; ?>
