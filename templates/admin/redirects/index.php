<div class="toolbar crud-toolbar">
    <div><p class="eyebrow">Routing</p><p class="lead">Reguły są stosowane przed rozwiązywaniem stron i kolekcji.</p></div>
</div>
<section class="form-section">
    <div class="section-heading"><div><p class="eyebrow">Nowa reguła</p><h2>Dodaj przekierowanie</h2></div><p>Źródło jest ścieżką witryny, cel może być ścieżką lub pełnym adresem HTTP(S).</p></div>
    <form class="redirect-form" method="post" action="/admin/redirects/create">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="revision" value="<?= $escape($revision) ?>">
        <label>Adres źródłowy<input name="source" placeholder="/stary-adres" required></label>
        <label>Adres docelowy<input name="target" placeholder="/nowy-adres" required></label>
        <label>Kod<select name="status"><?php foreach ([301, 302, 303, 307, 308] as $status): ?><option value="<?= $status ?>"><?= $status ?></option><?php endforeach; ?></select></label>
        <label class="check compact-check"><input type="checkbox" name="enabled" value="1" checked><span>Aktywne</span></label>
        <button type="submit">Dodaj regułę</button>
    </form>
</section>
<div class="redirect-list">
    <?php if ($rules === []): ?><div class="empty-state"><strong>Brak przekierowań.</strong><p>Dodaj pierwszą regułę powyżej.</p></div><?php endif; ?>
    <?php foreach ($rules as $rule): ?>
        <article class="form-section redirect-card">
            <form class="redirect-form" method="post" action="/admin/redirects/update">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="revision" value="<?= $escape($revision) ?>"><input type="hidden" name="id" value="<?= $escape($rule->id()) ?>">
                <label>Adres źródłowy<input name="source" value="<?= $escape($rule->source()) ?>" required></label>
                <label>Adres docelowy<input name="target" value="<?= $escape($rule->target()) ?>" required></label>
                <label>Kod<select name="status"><?php foreach ([301, 302, 303, 307, 308] as $status): ?><option value="<?= $status ?>"<?= $rule->status() === $status ? ' selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></label>
                <label class="check compact-check"><input type="checkbox" name="enabled" value="1"<?= $rule->enabled() ? ' checked' : '' ?>><span>Aktywne</span></label>
                <button type="submit">Zapisz</button>
            </form>
            <form method="post" action="/admin/redirects/delete" data-confirm="Usunąć tę regułę przekierowania?">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="revision" value="<?= $escape($revision) ?>"><input type="hidden" name="id" value="<?= $escape($rule->id()) ?>">
                <button class="button danger-text" type="submit">Usuń</button>
            </form>
        </article>
    <?php endforeach; ?>
</div>
