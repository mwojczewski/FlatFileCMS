<?php
$data = $editable->data();
$mapping = static fn(mixed $value): array => is_array($value) && !array_is_list($value) ? $value : [];
$strings = static function (mixed $value) use ($mapping): array {
    $result = [];
    foreach ($mapping($value) as $key => $item) if (is_string($key) && is_string($item)) $result[$key] = $item;
    return $result;
};
$seo = $mapping($data['seo'] ?? []);
$robots = $mapping($seo['robots'] ?? []);
$sort = $mapping($data['sort'] ?? []);
$pagination = $mapping($data['pagination'] ?? []);
$slug = $strings($data['slug'] ?? []);
$title = $strings($data['title'] ?? []);
$seoTitle = $strings($seo['title'] ?? []);
$seoDescription = $strings($seo['description'] ?? []);
$canonical = is_string($seo['canonical'] ?? null) ? $seo['canonical'] : '';
$filters = json_encode($data['filters'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
?>
<div class="toolbar crud-intro"><p>Zarządzaj routingiem, paginacją, sortowaniem i filtrami zapisanymi w <code>pagination.yml</code>.</p><a class="button secondary" href="/admin/pages">Wróć do listy</a></div>
<form class="stack crud-form" method="post" action="/admin/collections/update">
    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="identity" value="<?= $escape($identity->value()) ?>"><input type="hidden" name="revision" value="<?= $escape($editable->revision()->value()) ?>">
    <section class="form-section">
        <div class="section-heading"><div><p class="eyebrow">Podstawowe</p><h2>Ustawienia kolekcji</h2></div><p>Źródłem pozostają bezpośrednie katalogi potomne.</p></div>
        <div class="settings-grid">
            <div class="technical-path"><span>Ścieżka techniczna</span><code><?= $escape($identity->value()) ?></code></div>
            <label>Layout<select name="layout"><?php foreach ($layouts as $layout): ?><option value="<?= $escape($layout) ?>"<?= ($data['layout'] ?? 'collection') === $layout ? ' selected' : '' ?>><?= $escape($layout) ?></option><?php endforeach; ?></select></label>
            <label class="check toggle-card"><input type="checkbox" name="enabled" value="1"<?= ($data['enabled'] ?? true) === true ? ' checked' : '' ?>><span><strong>Kolekcja dostępna publicznie</strong><small>Wyłączenie ukrywa endpoint API i widok HTML.</small></span></label>
        </div>
    </section>
    <section class="form-section">
        <div class="section-heading"><div><p class="eyebrow">Języki</p><h2>Tytuły i adresy</h2></div><p>Język domyślny jest wymagany. Puste tłumaczenie użyje jego tytułu i sluga.</p></div>
        <div class="locale-grid"><?php foreach ($languages->languages() as $locale => $name): ?>
            <fieldset class="form-card locale-card"><legend><?= $escape($name) ?> <code><?= $escape($locale) ?></code></legend>
                <label>Tytuł kolekcji<input name="title[<?= $escape($locale) ?>]" value="<?= $escape($title[$locale] ?? '') ?>"<?= $locale === $languages->default() ? ' required' : '' ?>></label>
                <label>Publiczny slug<input name="slug[<?= $escape($locale) ?>]" value="<?= $escape($slug[$locale] ?? '') ?>"<?= $locale === $languages->default() ? ' required' : '' ?>></label>
                <label>Tytuł SEO<input name="seo_title[<?= $escape($locale) ?>]" value="<?= $escape($seoTitle[$locale] ?? '') ?>"></label>
                <label>Opis SEO<textarea name="seo_description[<?= $escape($locale) ?>]" maxlength="500"><?= $escape($seoDescription[$locale] ?? '') ?></textarea></label>
            </fieldset>
        <?php endforeach; ?></div>
    </section>
    <section class="form-section">
        <div class="section-heading"><div><p class="eyebrow">Zapytanie</p><h2>Sortowanie, paginacja i filtry</h2></div><p>Filtry pozostają rozszerzalną listą definicji.</p></div>
        <div class="settings-grid">
            <label>Pole sortowania<input name="sort_field" value="<?= $escape(is_string($sort['field'] ?? null) ? $sort['field'] : 'date') ?>" required></label>
            <label>Kierunek<select name="sort_direction"><option value="desc"<?= ($sort['direction'] ?? 'desc') === 'desc' ? ' selected' : '' ?>>Malejąco</option><option value="asc"<?= ($sort['direction'] ?? '') === 'asc' ? ' selected' : '' ?>>Rosnąco</option></select></label>
            <label>Elementów na stronę<input type="number" name="per_page" min="1" max="100" value="<?= (int) ($pagination['perPage'] ?? 12) ?>" required></label>
        </div>
        <label>Filtry (JSON)<textarea name="filters" class="code-textarea" spellcheck="false"><?= $escape($filters) ?></textarea></label>
        <p class="hint">Przykład: <code>[{"parameter":"category","field":"category","allowedValues":["news"]}]</code></p>
    </section>
    <section class="form-section">
        <div class="section-heading"><div><p class="eyebrow">Wyszukiwarki</p><h2>SEO kolekcji</h2></div></div>
        <fieldset class="form-card seo-card"><legend>SEO wspólne</legend>
            <label>Canonical URL<input name="canonical" value="<?= $escape($canonical) ?>" placeholder="/ścieżka lub https://example.com/ścieżka"></label>
            <div class="robots-grid"><label class="check"><input type="checkbox" name="robots_index" value="1"<?= ($robots['index'] ?? true) === true ? ' checked' : '' ?>><span><strong>Pozwól indeksować</strong></span></label><label class="check"><input type="checkbox" name="robots_follow" value="1"<?= ($robots['follow'] ?? true) === true ? ' checked' : '' ?>><span><strong>Pozwól śledzić linki</strong></span></label></div>
        </fieldset>
    </section>
    <div class="actions form-actions"><a class="button secondary" href="/admin/pages">Anuluj</a><button type="submit">Zapisz kolekcję</button></div>
</form>
