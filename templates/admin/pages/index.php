<div class="toolbar crud-toolbar">
    <div><p class="eyebrow">Zawartość</p><p class="lead">Zarządzaj fizycznym drzewem katalogu <code>pages/</code>.</p></div>
    <a class="button" href="/admin/pages/create">Dodaj stronę</a>
</div>
<div class="table-wrap crud-table"><table>
    <thead><tr><th>Strona</th><th>Stan</th><th></th></tr></thead>
    <tbody>
    <?php if ($entries === []): ?><tr><td class="table-empty" colspan="3">Brak stron. Dodaj pierwszą stronę, aby rozpocząć.</td></tr><?php endif; ?>
    <?php foreach ($entries as $entry): $identity = $entry['identity']->value(); $depth = count($entry['identity']->segments()) - 1; ?>
        <tr>
            <td class="page-cell"><span class="tree" style="--depth:<?= $depth ?>"><?= $escape($entry['title']) ?></span><small style="--depth:<?= $depth ?>"><?= $escape($identity) ?></small></td>
            <td><?php if ($entry['collection']): ?><span class="status collection">Kolekcja</span><?php elseif ($entry['enabled']): ?><span class="status on">Aktywna</span><?php else: ?><span class="status">Wyłączona</span><?php endif; ?></td>
            <td><div class="row-actions">
                <?php if (!$entry['identity']->isHomepage()): ?><a class="button compact child" href="/admin/pages/create?parent=<?= rawurlencode($identity) ?>">Dodaj podstronę</a><?php endif; ?>
                <a class="button compact secondary" href="<?= $entry['collection'] ? '/admin/collections/edit' : '/admin/pages/edit' ?>?path=<?= rawurlencode($identity) ?>">Edytuj</a>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
