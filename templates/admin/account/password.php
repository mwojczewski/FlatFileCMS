<?php if ($error !== ''): ?>
    <p class="error"><?= $escape($error) ?></p><?php endif; ?>
<div class="form-page-header">
    <div>
        <p class="eyebrow">Bezpieczeństwo konta</p>
        <h2>Zmień hasło</h2>
        <p class="lead">Po zapisaniu użyj nowego hasła przy następnym logowaniu.</p>
    </div>
    <div class="form-page-actions"><a class="button secondary" href="/admin/security">Wróć do konta</a></div>
</div>
<form method="post" action="/admin/account/password" class="stack crud-form editor-form account-password-form">
    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
    <section class="form-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Weryfikacja</p>
                <h2>Aktualne hasło</h2>
            </div>
            <p>Potwierdź swoją tożsamość przed wprowadzeniem zmiany.</p>
        </div>
        <label>Aktualne hasło<input type="password" name="current_password" required
                autocomplete="current-password"></label>
    </section>
    <section class="form-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Nowe dane</p>
                <h2>Nowe hasło</h2>
            </div>
            <p>Minimum 8 znaków, mała i wielka litera, cyfra oraz znak specjalny.</p>
        </div>
        <div class="settings-grid"><label>Nowe hasło<input type="password" name="new_password" required
                    autocomplete="new-password" minlength="8"></label><label>Powtórz nowe hasło<input type="password"
                    name="new_password_confirmation" required autocomplete="new-password" minlength="8"></label></div>
    </section>
    <div class="actions form-actions"><span class="form-actions-context">Zmiana dotyczy Twojego konta</span><a
            class="button secondary" href="/admin/security">Anuluj</a><button type="submit">Zmień hasło</button></div>
</form>