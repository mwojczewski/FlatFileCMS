<?php
$activeCount = count(array_filter($users, static fn($user): bool => $user->enabled()));
$showTechnicalAccounts = $actor->role()->value === 'ROLE_SUPERADMIN';
$technicalCount = $showTechnicalAccounts ? count(array_filter($users, static fn($user): bool => $user->role()->value === 'ROLE_SUPERADMIN')) : 0;
?>
<div class="pages-header users-header">
    <div>
        <p class="eyebrow">Dostęp do panelu</p>
        <h2>Administratorzy</h2>
        <p class="lead">Zarządzaj kontami widocznymi dla Twojej roli.</p>
    </div>
    <a class="button" href="/admin/users/create">Dodaj administratora</a>
</div>
<div class="pages-summary users-summary" aria-label="Podsumowanie kont">
    <div><strong><?= count($users) ?></strong><span>Widoczne konta</span></div>
    <div><strong><?= $activeCount ?></strong><span>Aktywne</span></div>
    <div><strong><?= count($users) - $activeCount ?></strong><span>Wyłączone</span></div>
    <?php if ($showTechnicalAccounts): ?>
        <div><strong><?= $technicalCount ?></strong><span>Techniczne</span></div><?php endif; ?>
</div>
<div class="table-wrap crud-table users-table">
    <table>
        <thead>
            <tr>
                <th>Konto</th>
                <th>Rola</th>
                <th>Stan</th>
                <th class="page-actions-column" aria-label="Akcje"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <div class="user-identity"><span class="user-avatar"
                                aria-hidden="true"><?= $escape($user->initials()) ?></span><span
                                class="page-cell"><strong><?= $escape($user->displayName()) ?><?= $user->id() === $actor->id() ? ' (Ty)' : '' ?></strong><small><?= $escape($user->email()) ?></small></span>
                        </div>
                    </td>
                    <td><span
                            class="page-kind"><?= $showTechnicalAccounts && $user->role()->value === 'ROLE_SUPERADMIN' ? 'Konto techniczne' : 'Administrator' ?></span>
                    </td>
                    <td><span class="status <?= $user->enabled() ? 'on' : '' ?>"><i
                                aria-hidden="true"></i><?= $user->enabled() ? 'Aktywne' : 'Wyłączone' ?></span></td>
                    <td class="page-actions-column">
                        <?php if ($user->role()->value === 'ROLE_ADMIN' && $user->id() !== $actor->id()): ?>
                            <details class="page-actions-menu" data-actions-menu>
                                <summary aria-label="Otwórz menu działań" title="Działania"><span aria-hidden="true">•••</span>
                                </summary>
                                <div class="page-actions-popover"><a
                                        href="/admin/users/edit?id=<?= $escape($user->publicId()) ?>"><span
                                            aria-hidden="true">✎</span>Edytuj konto</a></div>
                            </details>
                        <?php elseif ($showTechnicalAccounts && $user->role()->value === 'ROLE_SUPERADMIN'): ?><span
                                class="technical-lock" title="Konto zarządzane przez konsolę"
                                aria-label="Konto zarządzane przez konsolę">⌘</span><?php else: ?><a class="account-self-link"
                                href="/admin/security">Moje konto</a><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="form-hint users-footnote">Konta superadministratora są chronione i można nimi zarządzać wyłącznie z poziomu
    konsoli.</p>