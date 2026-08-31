<?php if ($passwordReset): ?><p class="success">Hasło zostało zmienione. Możesz się zalogować.</p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error"><?= $escape($error) ?></p><?php endif; ?>
<form method="post" action="/admin/login"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><label>Email<input type="email" name="email" required autocomplete="username"></label><label>Hasło<input type="password" name="password" required autocomplete="current-password"></label><button type="submit">Zaloguj się</button></form>
<p class="hint"><a href="/admin/password/forgot">Nie pamiętasz hasła?</a></p>
