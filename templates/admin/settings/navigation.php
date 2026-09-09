<div class="structure-header">
    <div>
        <p class="eyebrow">Struktura witryny</p>
        <h2>Menu nawigacyjne</h2>
        <p class="lead">Porządkuj linki przeciąganiem i buduj wielopoziomową hierarchię.</p>
    </div>
    <div class="structure-stats">
        <span><strong data-navigation-menu-count>0</strong> menu</span>
        <span><strong data-navigation-item-count>0</strong> pozycji</span>
    </div>
</div>
<div class="navigation-help">
    <span aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrows-expand"
            viewBox="0 0 16 16">
            <path fill-rule="evenodd"
                d="M1 8a.5.5 0 0 1 .5-.5h13a.5.5 0 0 1 0 1h-13A.5.5 0 0 1 1 8M7.646.146a.5.5 0 0 1 .708 0l2 2a.5.5 0 0 1-.708.708L8.5 1.707V5.5a.5.5 0 0 1-1 0V1.707L6.354 2.854a.5.5 0 1 1-.708-.708zM8 10a.5.5 0 0 1 .5.5v3.793l1.146-1.147a.5.5 0 0 1 .708.708l-2 2a.5.5 0 0 1-.708 0l-2-2a.5.5 0 0 1 .708-.708L7.5 14.293V10.5A.5.5 0 0 1 8 10" />
        </svg>
    </span>
    <p>
        <strong>Przeciągnij, aby zmienić kolejność.</strong> Upuszczenie pozycji na innej tworzy zagnieżdżenie.
    </p>
</div>
<form class="stack navigation-form" method="post" action="/admin/navigation" data-navigation-form>
    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
    <input type="hidden" name="revision" value="<?= $escape($revision) ?>" data-navigation-revision>
    <input type="hidden" name="payload" data-navigation-payload>
    <div class="navigation-editor" data-navigation-editor></div>
    <div class="actions form-actions navigation-autosave-bar editor-primary-actions">
        <span class="navigation-save-state" data-navigation-save-state role="status" aria-live="polite">
            <i aria-hidden="true"></i>
            <span>Wszystkie zmiany zapisane</span>
        </span>
        <span class="form-actions-context">Zmiany dotyczą wszystkich wersji językowych.</span>
        <button type="button" class="button secondary" data-navigation-add-menu>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-circle"
                viewBox="0 0 16 16">
                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16" />
                <path
                    d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4" />
            </svg>
            Dodaj menu
        </button>
    </div>
</form>
<dialog class="navigation-dialog" data-navigation-dialog aria-labelledby="navigation-dialog-title">
    <form class="stack navigation-dialog-form" data-navigation-dialog-form>
        <div class="navigation-dialog-heading">
            <div>
                <p class="eyebrow">Pozycja menu</p>
                <h2 id="navigation-dialog-title" data-navigation-dialog-title>Edytuj pozycję</h2>
            </div>
            <button type="button" class="dialog-close" data-navigation-dialog-close aria-label="Zamknij">×</button>
        </div>
        <div class="navigation-dialog-fields" data-navigation-dialog-fields></div>
        <div class="actions navigation-dialog-footer">
            <button type="button" class="button secondary" data-navigation-dialog-close>Anuluj</button>
            <button type="submit">Zapisz pozycję</button>
        </div>
    </form>
</dialog>
<?php foreach (['navigation-data' => $navigation, 'navigation-languages' => $languagesData, 'navigation-destinations' => $destinations] as $id => $payload): ?>
    <script type="application/json"
        id="<?= $id ?>"><?= json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<?php endforeach; ?>