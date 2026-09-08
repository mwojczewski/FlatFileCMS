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
        <span class="security-status-icon" aria-hidden="true"><?= $credentialCount > 0 ? '✓' : '!' ?></span>
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
        <span class="account-action-icon" aria-hidden="true">•••</span>
        <span>
            <strong>Zmień hasło</strong>
            <small>Ustaw nowe hasło własnego konta.</small>
        </span>
        <span aria-hidden="true">→</span>
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
        <span aria-hidden="true">→</span>
    </a>
</div>