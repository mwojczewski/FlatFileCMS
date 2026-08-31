<?php
$string = static fn(mixed $value): string => is_string($value) ? $value : '';
$mapping = static fn(mixed $value): array => is_array($value) && !array_is_list($value) ? $value : [];
$json = static fn(array $value): string => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
?>
<div class="toolbar crud-toolbar"><div><p class="eyebrow">Ustawienia witryny</p><p class="lead">Sekrety pozostają w środowisku, a poniższe grupy zapisują jawne pliki konfiguracyjne.</p></div><a class="button secondary" href="/admin/navigation">Edytuj nawigację</a></div>
<nav class="settings-submenu" aria-label="Sekcje konfiguracji">
    <a href="#site-settings">Witryna</a><a href="#seo-settings">SEO</a><a href="#media-settings">Multimedia</a><a href="#text-files">Pliki tekstowe</a>
</nav>
<form class="stack crud-form" method="post" action="/admin/settings">
    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="revision" value="<?= $escape($document->revision()->value()) ?>">
    <?php require __DIR__ . '/sections/site.php'; ?>
    <?php require __DIR__ . '/sections/seo.php'; ?>
    <?php require __DIR__ . '/sections/media.php'; ?>
    <div class="actions form-actions"><button type="submit">Zapisz konfigurację witryny</button></div>
</form>
<?php require __DIR__ . '/sections/text-files.php'; ?>
