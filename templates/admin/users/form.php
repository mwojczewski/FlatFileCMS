<?php
$editing = $user !== null;
$currentEmail = $email ?? ($editing ? $user->email() : '');
$currentFirstName = $firstName ?? ($editing ? $user->firstName() : '');
$currentLastName = $lastName ?? ($editing ? $user->lastName() : '');
$currentEnabled = $enabled ?? ($editing ? $user->enabled() : true);
?>
<?php if ($error !== ''): ?>
    <p class="error"><?= $escape($error) ?></p><?php endif; ?>
<div class="form-page-header">
    <div><p class="eyebrow"><?= $editing ? 'Edycja dostępu' : 'Nowe konto' ?></p><h2><?= $editing ? $escape($user->displayName()) : 'Dodaj administratora' ?></h2><p class="lead"><?= $editing ? 'Zmień dane, hasło lub dostęp użytkownika do panelu.' : 'Utwórz konto z rolą administratora panelu.' ?></p></div>
    <div class="form-page-actions"><a class="button secondary" href="/admin/users">Wróć do administratorów</a></div>
</div>
<form class="stack crud-form editor-form account-editor-form" method="post" action="<?= $editing ? '/admin/users/update' : '/admin/users/create' ?>">
    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= $escape($user->publicId()) ?>"><?php endif; ?>
    <section class="form-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Konto</p>
                <h2>Dane administratora</h2>
            </div>
            <p>Nowe konta zawsze otrzymują rolę ROLE_ADMIN.</p>
        </div>
        <div class="settings-grid">
            <label>Imię<input type="text" name="first_name" value="<?= $escape($currentFirstName) ?>" required
                    autocomplete="given-name" maxlength="100"></label>
            <label>Nazwisko<input type="text" name="last_name" value="<?= $escape($currentLastName) ?>" required
                    autocomplete="family-name" maxlength="100"></label>
            <label>Email<input type="email" name="email" value="<?= $escape($currentEmail) ?>" required
                    autocomplete="email"></label>
            <?php if ($editing): ?>
                <label class="check toggle-card"><input type="checkbox" name="enabled" value="1" <?= $currentEnabled ? ' checked' : '' ?>><span><strong>Konto aktywne</strong><small>Wyłączone konto nie może zalogować się
                            do panelu.</small></span></label>
            <?php endif; ?>
        </div>
    </section>
    <section class="form-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Hasło</p>
                <h2><?= $editing ? 'Opcjonalna zmiana hasła' : 'Hasło początkowe' ?></h2>
            </div>
            <p>Minimum 8 znaków i wymagane różne klasy znaków.</p>
        </div>
        <div class="settings-grid">
            <label>Hasło<input type="password" name="password" <?= $editing ? '' : ' required' ?>
                    autocomplete="new-password" minlength="8"></label>
            <label>Powtórz hasło<input type="password" name="password_confirmation" <?= $editing ? '' : ' required' ?>
                    autocomplete="new-password" minlength="8"></label>
        </div>
    </section>
    <div class="actions form-actions"><span class="form-actions-context"><?= $editing ? 'Edytujesz istniejące konto' : 'Konto otrzyma rolę administratora' ?></span><a class="button secondary" href="/admin/users">Anuluj</a><button
            type="submit"><?= $editing ? 'Zapisz zmiany' : 'Utwórz konto' ?></button></div>
</form>
<?php if ($editing): ?>
    <section class="danger-zone">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Zaawansowane</p>
                <h2>Usuń konto</h2>
            </div>
            <p>Usunięcie konta bezpowrotnie odbierze dostęp do panelu.</p>
        </div>
        <form method="post" action="/admin/users/delete" data-confirm="Usunąć konto administratora?">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="id"
                value="<?= $escape($user->publicId()) ?>">
            <button type="submit" class="danger" <?= $user->id() === $actor?->id() ? ' disabled' : '' ?>>Usuń
                administratora</button>
        </form>
    </section>
<?php endif; ?>
