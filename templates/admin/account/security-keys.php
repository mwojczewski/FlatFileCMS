<div class="form-page-header">
    <div><p class="eyebrow">Drugi składnik logowania</p><h2>Klucze bezpieczeństwa</h2><p class="lead">Passkey lub klucz sprzętowy chroni konto nawet po przejęciu hasła.</p></div>
    <div class="form-page-actions"><a class="button secondary" href="/admin/security">Wróć do konta</a></div>
</div>
<section class="security-keys-summary">
    <span class="security-status-icon <?= $credentials === [] ? '' : 'is-active' ?>" aria-hidden="true"><?= $credentials === [] ? '!' : '✓' ?></span>
    <div><strong><?= $credentials === [] ? 'Brak dodatkowego składnika' : 'Konto chronione kluczem' ?></strong><p><?= $credentials === [] ? 'Zarejestruj pierwszy klucz, aby włączyć uwierzytelnianie dwuskładnikowe.' : 'Aktywne klucze: ' . count($credentials) . '. Każdy z nich może potwierdzić logowanie.' ?></p></div>
</section>
<div class="table-wrap crud-table security-keys-table"><table><thead><tr><th>Klucz</th><th>Rodzaj</th><th class="page-actions-column" aria-label="Akcje"></th></tr></thead><tbody>
<?php if ($credentials === []): ?><tr><td colspan="3" class="table-empty">Brak zarejestrowanych kluczy.</td></tr><?php endif; ?>
<?php foreach ($credentials as $credential): ?><tr><td><div class="security-key-identity"><span aria-hidden="true">⌁</span><span class="page-cell"><strong><?= $escape($credential->name()) ?></strong><small>Klucz nr <?= $credential->id() ?></small></span></div></td><td><span class="page-kind"><?= $credential->transports() === [] ? 'Passkey / WebAuthn' : $escape(implode(', ', $credential->transports())) ?></span></td><td class="page-actions-column"><form method="post" action="/admin/account/security-keys/delete" data-confirm="Usunąć klucz bezpieczeństwa?"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="id" value="<?= $credential->id() ?>"><button type="submit" class="button compact danger-text">Usuń</button></form></td></tr><?php endforeach; ?>
</tbody></table></div>
<section class="form-section security-key-form">
    <div class="section-heading"><div><p class="eyebrow">Nowy klucz</p><h2>Dodaj passkey lub klucz sprzętowy</h2></div><p>Przeglądarka poprosi o użycie urządzenia po potwierdzeniu hasła.</p></div>
    <form data-webauthn-register><label>Nazwa klucza<input name="key_name" maxlength="80" required value="Mój klucz"></label><label>Aktualne hasło<input type="password" name="current_password" required autocomplete="current-password"></label><button type="submit">Zarejestruj klucz</button></form><p class="error" data-auth-error role="alert" aria-live="assertive"></p>
</section>
