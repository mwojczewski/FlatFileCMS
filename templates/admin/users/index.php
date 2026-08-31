<div class="toolbar crud-toolbar">
    <div><p class="eyebrow">Dostęp do panelu</p><p class="lead">Zarządzaj kontami administracyjnymi widocznymi dla Twojej roli.</p></div>
    <a class="button" href="/admin/users/create">Dodaj administratora</a>
</div>
<div class="table-wrap crud-table">
    <table>
        <thead><tr><th>Konto</th><th>Rola</th><th>Stan</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td class="page-cell"><strong><?= $escape($user->email()) ?></strong><small>ID: <?= $user->id() ?><?= $user->id() === $actor->id() ? ' · Twoje konto' : '' ?></small></td>
                <td><?= $user->role()->value === 'ROLE_SUPERADMIN' ? '<span class="status">Konto techniczne</span>' : '<span class="status collection">Administrator</span>' ?></td>
                <td><span class="status <?= $user->enabled() ? 'on' : '' ?>"><?= $user->enabled() ? 'Aktywne' : 'Wyłączone' ?></span></td>
                <td><div class="row-actions">
                    <?php if ($user->role()->value === 'ROLE_ADMIN'): ?>
                        <a class="button compact secondary" href="/admin/users/edit?id=<?= $user->id() ?>">Edytuj</a>
                    <?php else: ?>
                        <span class="muted">Zarządzane wyłącznie przez CLI</span>
                    <?php endif; ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
