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
    <div>
        <strong><?= count($users) ?></strong>
        <span>Widoczne konta</span>
    </div>
    <div>
        <strong><?= $activeCount ?></strong>
        <span>Aktywne</span>
    </div>
    <div>
        <strong><?= count($users) - $activeCount ?></strong>
        <span>Wyłączone</span>
    </div>
    <?php if ($showTechnicalAccounts): ?>
        <div>
            <strong><?= $technicalCount ?></strong>
            <span>Techniczne</span>
        </div>
    <?php endif; ?>
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
                        <div class="user-identity">
                            <span class="user-avatar" aria-hidden="true"><?= $escape($user->initials()) ?></span>
                            <span class="page-cell">
                                <strong><?= $escape($user->displayName()) ?><?= $user->id() === $actor->id() ? ' (Ty)' : '' ?></strong>
                                <small><?= $escape($user->email()) ?></small>
                            </span>
                        </div>
                    </td>
                    <td>
                        <span
                            class="page-kind"><?= $showTechnicalAccounts && $user->role()->value === 'ROLE_SUPERADMIN' ? 'Konto techniczne' : 'Administrator' ?></span>
                    </td>
                    <td>
                        <span class="status <?= $user->enabled() ? 'on' : '' ?>">
                            <i aria-hidden="true"></i><?= $user->enabled() ? 'Aktywne' : 'Wyłączone' ?>
                        </span>
                    </td>
                    <td class="page-actions-column">
                        <?php if ($user->role()->value === 'ROLE_ADMIN' && $user->id() !== $actor->id()): ?>
                            <details class="page-actions-menu" data-actions-menu>
                                <summary aria-label="Otwórz menu działań" title="Działania">
                                    <span aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                            <path
                                                d="M3 9.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3m5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3m5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3" />
                                        </svg>
                                    </span>
                                </summary>
                                <div class="page-actions-popover">
                                    <a href="/admin/users/edit?id=<?= $escape($user->publicId()) ?>">
                                        <span aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                <path
                                                    d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z" />
                                                <path fill-rule="evenodd"
                                                    d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z" />
                                            </svg>
                                        </span>Edytuj konto
                                    </a>
                                </div>
                            </details>
                        <?php elseif ($showTechnicalAccounts && $user->role()->value === 'ROLE_SUPERADMIN'): ?>
                            <span class="technical-lock" title="Konto zarządzane przez konsolę"
                                aria-label="Konto zarządzane przez konsolę">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path
                                        d="M2 3a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h5.5a.5.5 0 0 1 0 1H2a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v4a.5.5 0 0 1-1 0V4a1 1 0 0 0-1-1z" />
                                    <path
                                        d="M3.146 5.146a.5.5 0 0 1 .708 0L5.177 6.47a.75.75 0 0 1 0 1.06L3.854 8.854a.5.5 0 1 1-.708-.708L4.293 7 3.146 5.854a.5.5 0 0 1 0-.708M5.5 9a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1H6a.5.5 0 0 1-.5-.5M16 12.5a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0m-4.854-1.354a.5.5 0 0 0 0 .708l.647.646-.647.646a.5.5 0 0 0 .708.708l.646-.647.646.647a.5.5 0 0 0 .708-.708l-.647-.646.647-.646a.5.5 0 0 0-.708-.708l-.646.647-.646-.647a.5.5 0 0 0-.708 0" />
                                </svg>
                            </span>
                        <?php else: ?>
                            <a class="account-self-link" href="/admin/security">Moje konto</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="form-hint users-footnote">Konta superadministratora są chronione i można nimi zarządzać wyłącznie z poziomu
    konsoli.</p>