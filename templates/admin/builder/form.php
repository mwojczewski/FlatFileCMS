<div class="block-form-heading">
    <a class="editor-back" href="/admin/pages/builder?path=<?= rawurlencode($identity->value()) ?>"
        aria-label="Wróć do edytora">←</a>
    <span class="block-symbol" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="8" height="8" rx="2" />
            <rect x="13" y="3" width="8" height="5" rx="2" />
            <rect x="13" y="10" width="8" height="11" rx="2" />
            <rect x="3" y="13" width="8" height="8" rx="2" />
        </svg></span>
    <div>
        <p class="eyebrow"><?= $id === null ? 'Nowy blok' : 'Edycja bloku' ?></p>
        <h2><?= $escape($name) ?></h2><code><?= $escape($definition->type()) ?></code>
    </div>
</div>
<form class="stack block-form block-inspector-form" method="post" action="<?= $escape($action) ?>"
    data-page-identity="<?= $escape($identity->value()) ?>">
    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
    <input type="hidden" name="identity" value="<?= $escape($identity->value()) ?>">
    <input type="hidden" name="type" value="<?= $escape($definition->type()) ?>">
    <input type="hidden" name="revision" value="<?= $escape($revision->value()) ?>">
    <?php if ($id !== null): ?><input type="hidden" name="id" value="<?= $escape($id) ?>"><?php endif; ?>
    <div class="block-form-fields"><?= $fields ?></div>
    <div class="actions footer-actions block-form-actions"><span><strong><?= $escape($name) ?></strong><small>Zmiany
                zostaną zapisane w treści strony.</small></span><a class="button secondary"
            href="/admin/pages/builder?path=<?= rawurlencode($identity->value()) ?>">Anuluj</a><button
            type="submit">Zapisz blok</button></div>
</form>