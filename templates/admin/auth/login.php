<?php if ($passwordReset): ?>
    <p class="success">Hasło zostało zmienione. Możesz się zalogować.</p><?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="error"><?= $escape($error) ?></p><?php endif; ?>
<p class="auth-lead">Wprowadź dane konta, aby przejść do zarządzania witryną.</p>
<form class="auth-form" method="post" action="/admin/login">
    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
    <label><span>Email</span><input type="email" name="email" required autocomplete="username"
            placeholder="nazwa@example.com"></label>
    <label><span>Hasło</span><input type="password" name="password" required autocomplete="current-password"
            placeholder="Wprowadź hasło"></label>
    <div class="auth-form-meta"><a href="/admin/password/forgot">Nie pamiętasz hasła?</a></div>
    <button type="submit">Zaloguj się do panelu</button>
</form>