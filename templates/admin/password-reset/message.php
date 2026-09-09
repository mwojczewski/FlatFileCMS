<div class="auth-result <?= $escape($type) ?>"><span aria-hidden="true"><?= $type === 'success' ? '✓' : '!' ?></span>
    <p><?= $escape($message) ?></p>
</div>
<a class="button secondary auth-result-action" href="<?= $escape($url) ?>"><?= $escape($label) ?></a>