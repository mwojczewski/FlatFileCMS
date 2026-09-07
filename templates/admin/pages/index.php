<div class="toolbar crud-toolbar">
    <div>
        <p class="eyebrow">Zawartość</p>
        <p class="lead">Zarządzaj fizycznym drzewem katalogu <code>pages/</code>.</p>
    </div>
    <a class="button" href="/admin/pages/create">Dodaj stronę</a>
</div>
<div class="table-wrap crud-table">
    <table>
        <thead>
            <tr>
                <th>Strona</th>
                <th>Stan</th>
                <th class="page-actions-column" aria-label="Akcje"></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($entries === []): ?>
                <tr>
                    <td class="table-empty" colspan="3">Brak stron. Dodaj pierwszą stronę, aby rozpocząć.</td>
                </tr><?php endif; ?>
            <?php foreach ($entries as $entry):
                $identity = $entry['identity']->value();
                $depth = count($entry['identity']->segments()) - 1; ?>
                <tr>
                    <td class="page-cell">
                        <span class="tree" style="--depth:<?= $depth ?>"><?= $escape($entry['title']) ?></span>
                        <small style="--depth:<?= $depth ?>"><?= $escape($identity) ?></small>
                    </td>
                    <td>
                        <?php if ($entry['collection']): ?>
                            <span class="status collection">Kolekcja</span>
                        <?php elseif ($entry['enabled']): ?><span class="status on">Aktywna</span>
                        <?php else: ?>
                            <span class="status">Wyłączona</span><?php endif; ?>
                    </td>
                    <td class="page-actions-column">
                        <div class="row-actions page-row-actions">
                            <?php if (!$entry['identity']->isHomepage()): ?>
                                <a class="icon-button page-action-add"
                                    href="/admin/pages/create?parent=<?= rawurlencode($identity) ?>"
                                    aria-label="Dodaj podstronę" title="Dodaj podstronę">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M6 2h9l5 5v15H6z" />
                                        <path d="M14 2v6h6M12 12v6m-3-3h6" />
                                    </svg>
                                </a>
                            <?php endif; ?>
                            <?php if ($entry['collection']): ?>
                                <span class="icon-button page-action-builder is-disabled" aria-disabled="true"
                                    aria-label="Edytor bloków jest niedostępny dla kolekcji"
                                    title="Edytor bloków jest niedostępny dla kolekcji">
                                    <?php else: ?>
                                    <a class="icon-button page-action-builder"
                                        href="/admin/pages/builder?path=<?= rawurlencode($identity) ?>"
                                        aria-label="Otwórz edytor bloków" title="Edytor bloków">
                                            <?php endif; ?>
                                    <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <rect x="3" y="3" width="7" height="7" rx="1" />
                                        <rect x="14" y="3" width="7" height="7" rx="1" />
                                        <rect x="3" y="14" width="7" height="7" rx="1" />
                                        <rect x="14" y="14" width="7" height="7" rx="1" />
                                    </svg>
                                            <?php if ($entry['collection']): ?></span><?php else: ?></a><?php endif; ?>
                            <a class="icon-button page-action-edit"
                                href="<?= $entry['collection'] ? '/admin/collections/edit' : '/admin/pages/edit' ?>?path=<?= rawurlencode($identity) ?>"
                                aria-label="Edytuj ustawienia" title="Edytuj ustawienia">
                                <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 20h9" />
                                    <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4z" />
                                </svg>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>