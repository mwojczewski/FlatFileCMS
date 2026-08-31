<div class="toolbar crud-intro">
    <p>Zarządzaj metadanymi albo przejdź do układu bloków tej strony.</p>
    <span class="actions"><a class="button secondary" href="/admin/media?path=<?= rawurlencode($identity->value()) ?>">Multimedia</a><a class="button child" href="/admin/pages/builder?path=<?= rawurlencode($identity->value()) ?>">Otwórz page builder</a></span>
</div>
<?= $form ?>
