<?php
$data = $editable?->data() ?? [];
$mapping = static fn(mixed $value): array => is_array($value) && !array_is_list($value) ? $value : [];
$strings = static function (mixed $value) use ($mapping): array { $result = []; foreach ($mapping($value) as $key => $item) if (is_string($key) && is_string($item)) $result[$key] = $item; return $result; };
$homepage = $identity?->isHomepage() ?? false;
$currentIdentity = $identity?->value() ?? $identityPrefix;
$enabled = ($data['enabled'] ?? true) === true;
$currentLayout = is_string($data['layout'] ?? null) ? $data['layout'] : '';
$titleValues = $strings($data['title'] ?? []);
$slugValues = $strings($data['slug'] ?? []);
$seo = $mapping($data['seo'] ?? []);
$seoTitles = $strings($seo['title'] ?? []);
$seoDescriptions = $strings($seo['description'] ?? []);
$robots = $mapping($seo['robots'] ?? []);
$canonical = is_string($seo['canonical'] ?? null) ? $seo['canonical'] : '';
$revision = $editable?->revision()->value();
$creating = $editable === null;
?>
<form class="stack crud-form" method="post" action="<?= $escape($action) ?>"<?= $creating ? ' data-canonical-suggest data-site-url="' . $escape($siteUrl) . '" data-canonical-base-path="' . $escape($canonicalBasePath) . '"' : '' ?>>
    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><?php if ($revision !== null): ?><input type="hidden" name="revision" value="<?= $escape($revision) ?>"><?php endif; ?>
    <section class="form-section">
        <div class="section-heading"><div><p class="eyebrow">Podstawowe</p><h2>Ustawienia strony</h2></div><p>Widoczność, położenie i szablon dokumentu.</p></div>
        <div class="settings-grid"><div>
            <?php if ($creating): ?><label>Ścieżka techniczna<input name="identity" value="<?= $escape($currentIdentity) ?>" required placeholder="np. blog/nowy-wpis"></label>
            <?php else: ?><input type="hidden" name="identity" value="<?= $escape($currentIdentity) ?>"><div class="technical-path"><span>Ścieżka techniczna</span><code><?= $escape($currentIdentity) ?></code></div><?php endif; ?>
        </div>
        <label>Layout<select name="layout"><option value="">Domyślny z setup.yml</option><?php foreach ($layouts as $layout): ?><option value="<?= $escape($layout) ?>"<?= $layout === $currentLayout ? ' selected' : '' ?>><?= $escape($layout) ?></option><?php endforeach; ?></select></label>
        <label class="check toggle-card"><input type="checkbox" name="enabled" value="1"<?= $enabled ? ' checked' : '' ?>><span><strong>Strona dostępna publicznie</strong><small>Wyłączenie ukrywa stronę w publicznym API i renderowaniu HTML.</small></span></label></div>
    </section>
    <section class="form-section">
        <div class="section-heading"><div><p class="eyebrow">Języki</p><h2>Treść i adresy</h2></div><p>Język domyślny jest wymagany. Puste tłumaczenie automatycznie użyje jego treści i sluga.</p></div>
        <div class="locale-grid"><?php foreach ($languages->languages() as $locale => $name): ?>
            <fieldset class="form-card locale-card"><legend><?= $escape($name) ?> <code><?= $escape($locale) ?></code></legend>
                <label>Tytuł strony<input name="title[<?= $escape($locale) ?>]" value="<?= $escape($titleValues[$locale] ?? '') ?>"<?= $locale === $languages->default() ? ' required' : '' ?>></label>
                <?php if (!$homepage): ?><label>Publiczny slug<input name="slug[<?= $escape($locale) ?>]" value="<?= $escape($slugValues[$locale] ?? '') ?>"<?= $locale === $languages->default() ? ' required' : '' ?><?= $creating && $locale === $languages->default() ? ' data-public-slug' : '' ?>></label><?php endif; ?>
                <label>Tytuł SEO<input name="seo_title[<?= $escape($locale) ?>]" value="<?= $escape($seoTitles[$locale] ?? '') ?>"></label>
                <label>Opis SEO<textarea name="seo_description[<?= $escape($locale) ?>]" maxlength="500"><?= $escape($seoDescriptions[$locale] ?? '') ?></textarea></label>
            </fieldset>
        <?php endforeach; ?></div>
    </section>
    <section class="form-section">
        <div class="section-heading"><div><p class="eyebrow">Wyszukiwarki</p><h2>Ustawienia SEO</h2></div><p>Canonical nowej strony zostanie podpowiedziany z URL witryny i domyślnego sluga.</p></div>
        <fieldset class="form-card seo-card"><legend>SEO wspólne</legend>
            <label>Canonical URL<input name="canonical" value="<?= $escape($canonical) ?>" placeholder="https://example.com/ścieżka"<?= $creating ? ' data-canonical' : '' ?>></label>
            <div class="robots-grid"><label class="check"><input type="checkbox" name="robots_index" value="1"<?= ($robots['index'] ?? true) === true ? ' checked' : '' ?>><span><strong>Pozwól indeksować</strong><small>Wyszukiwarki mogą umieścić stronę w wynikach.</small></span></label><label class="check"><input type="checkbox" name="robots_follow" value="1"<?= ($robots['follow'] ?? true) === true ? ' checked' : '' ?>><span><strong>Pozwól śledzić linki</strong><small>Roboty mogą przechodzić do odnośników na stronie.</small></span></label></div>
        </fieldset>
    </section>
    <div class="actions form-actions"><a class="button secondary" href="/admin/pages">Anuluj</a><button type="submit">Zapisz zmiany</button></div>
</form>
<?php if (!$creating && !$homepage && $revision !== null): ?>
<section class="danger-zone">
    <div class="section-heading"><div><p class="eyebrow">Zaawansowane</p><h2>Operacje na katalogu</h2></div><p>Te działania zmieniają fizyczną strukturę plików strony.</p></div>
    <div class="danger-grid">
        <form class="danger-action" method="post" action="/admin/pages/move"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="identity" value="<?= $escape($currentIdentity) ?>"><input type="hidden" name="revision" value="<?= $escape($revision) ?>"><h3>Przenieś stronę</h3><p>Zmień ścieżkę techniczną bez utraty zawartości.</p><label>Nowa ścieżka techniczna<input name="destination" value="<?= $escape($currentIdentity) ?>" required></label><button type="submit" class="warning">Przenieś katalog</button></form>
        <form class="danger-action delete-action" method="post" action="/admin/pages/delete"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="identity" value="<?= $escape($currentIdentity) ?>"><input type="hidden" name="revision" value="<?= $escape($revision) ?>"><h3>Usuń stronę</h3><p>Usunięty zostanie cały katalog wraz z podstronami i multimediami.</p><label>Wpisz <code>delete</code>, aby potwierdzić<input name="confirmation" required autocomplete="off"></label><button type="submit" class="danger">Usuń katalog bezpowrotnie</button></form>
    </div>
</section>
<?php endif; ?>
