<?php if ($passwordChanged): ?>
    <p class="success">Hasło zostało zmienione.</p><?php endif; ?>
<div class="account-overview">
    <section class="account-profile-card">
        <span class="account-profile-avatar" aria-hidden="true"><?= $escape($user->initials()) ?></span>
        <div>
            <p class="eyebrow">Zalogowane konto</p>
            <h2><?= $escape($user->displayName()) ?></h2>
            <p><?= $escape($user->email()) ?></p>
        </div>
        <span
            class="page-kind"><?= $user->role()->value === 'ROLE_SUPERADMIN' ? 'Superadministrator' : 'Administrator' ?>
        </span>
    </section>
    <section class="account-security-status <?= $credentialCount > 0 ? 'is-strong' : '' ?>">
        <span class="security-status-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                class="bi bi-exclamation-lg" viewBox="0 0 16 16">
                <?= $credentialCount > 0 ?
                    '<path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="m10.97 4.97-.02.022-3.473 4.425-2.093-2.094a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05"/>'
                    :
                    '<path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/>' ?>
            </svg>
        </span>
        <div>
            <strong><?= $credentialCount > 0 ? 'Dodatkowa ochrona aktywna' : 'Podstawowa ochrona konta' ?></strong>
            <p>
                <?= $credentialCount > 0 ? 'Logowanie wymaga hasła i zarejestrowanego klucza.' : 'Dodaj klucz bezpieczeństwa, aby chronić konto drugim składnikiem.' ?>
            </p>
        </div>
    </section>
</div>
<div class="account-action-grid">
    <a href="/admin/account/password">
        <span class="account-action-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-three-dots"
                viewBox="0 0 16 16">
                <path
                    d="M3 9.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3m5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3m5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3" />
            </svg>
        </span>
        <span>
            <strong>Zmień hasło</strong>
            <small>Ustaw nowe hasło własnego konta.</small>
        </span>
        <span aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-right"
                viewBox="0 0 16 16">
                <path fill-rule="evenodd"
                    d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8" />
            </svg>
        </span>
    </a>
    <a href="/admin/account/security-keys">
        <span class="account-action-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-usb-drive"
                viewBox="0 0 16 16">
                <path
                    d="M6 .5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 .5.5v4H6zM7 1v1h1V1zm2 0v1h1V1zM6 5a1 1 0 0 0-1 1v8.5A1.5 1.5 0 0 0 6.5 16h4a1.5 1.5 0 0 0 1.5-1.5V6a1 1 0 0 0-1-1zm0 1h5v8.5a.5.5 0 0 1-.5.5h-4a.5.5 0 0 1-.5-.5z" />
            </svg>
        </span>
        <span>
            <strong>Klucze bezpieczeństwa</strong>
            <small><?= $credentialCount === 0 ? 'Brak kluczy — logowanie tylko hasłem.' : "Zarejestrowane klucze: {$credentialCount}" ?></small>
        </span>
        <span aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-right"
                viewBox="0 0 16 16">
                <path fill-rule="evenodd"
                    d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8" />
            </svg>
        </span>
    </a>
</div>