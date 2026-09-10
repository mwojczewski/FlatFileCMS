<p class="auth-lead">Podaj adres przypisany do konta administratora. Jeśli konto istnieje, wyślemy instrukcję zmiany
    hasła.</p>
<form class="auth-form" method="post" action="/admin/password/forgot">
    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
    <label><span>Email</span><input type="email" name="email" required autocomplete="email"
            placeholder="nazwa@example.com"></label>
    <button type="submit">Wyślij link resetujący</button>
</form>
<p class="auth-back-link"><a href="/admin/login">← Wróć do logowania</a></p>