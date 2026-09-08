<?php if ($error !== ''): ?><p class="error"><?= $escape($error) ?></p><?php endif; ?>
<p class="auth-lead">Ustaw nowe, unikalne hasło dla swojego konta administratora.</p>
<form method="post" action="/admin/password/reset" class="auth-form">
    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="token" value="<?= $escape($token) ?>">
    <label><span>Nowe hasło</span><input type="password" name="password" required autocomplete="new-password" minlength="8"></label>
    <label><span>Powtórz hasło</span><input type="password" name="password_confirmation" required autocomplete="new-password" minlength="8"></label>
    <p class="auth-password-hint"><span aria-hidden="true">i</span>Minimum 8 znaków, mała i wielka litera, cyfra oraz znak specjalny.</p>
    <button type="submit">Zapisz nowe hasło</button>
</form>
