<?php
$actions = [
    ['/admin/pages/builder/toggle', null, 'builder-action-toggle'],
    ['/admin/pages/builder/duplicate', 'Duplikuj blok', 'builder-action-duplicate'],
    ['/admin/pages/builder/delete', 'Usuń blok', 'builder-action-remove'],
];
$enabledCount = count(array_filter($blocks, static fn(array $block): bool => $block['enabled']));
?>
<div class="editor-topbar">
    <div class="editor-page-identity">
        <a class="editor-back" href="/admin/pages" aria-label="Wróć do stron">←</a>
        <div><p class="eyebrow">Edytor strony</p><h2><?= $escape($identity->isHomepage() ? 'Strona główna' : $identity->value()) ?></h2></div>
        <span class="editor-state"><i aria-hidden="true"></i><?= $enabledCount ?> z <?= count($blocks) ?> aktywnych</span>
    </div>
    <div class="actions editor-primary-actions">
        <a class="button secondary" href="<?= $escape($previewUrl) ?>" target="_blank" rel="noopener">Podgląd <span aria-hidden="true">↗</span></a>
        <a class="button" href="/admin/pages/builder/picker?path=<?= rawurlencode($identity->value()) ?>"><span aria-hidden="true">＋</span> Dodaj blok</a>
    </div>
</div>

<div class="block-editor-layout">
    <main class="block-editor-canvas">
        <header class="canvas-heading"><div><span class="canvas-dot" aria-hidden="true"></span><strong>Zawartość strony</strong></div><small>Przeciągnij bloki, aby zmienić ich kolejność</small></header>
        <div class="builder-list" data-builder-list>
            <?php if ($blocks === []): ?>
                <div class="empty-state builder-empty"><span aria-hidden="true">＋</span><strong>Rozpocznij budowę strony</strong><p>Dodaj pierwszy blok z biblioteki komponentów.</p><a class="button" href="/admin/pages/builder/picker?path=<?= rawurlencode($identity->value()) ?>">Wybierz blok</a></div>
            <?php endif; ?>
            <?php foreach ($blocks as $block): ?>
                <article class="builder-item<?= $block['enabled'] ? '' : ' disabled' ?>" draggable="true" data-block-id="<?= $escape($block['id']) ?>">
                    <button type="button" class="drag-handle" aria-label="Przeciągnij blok" title="Zmień kolejność">⋮⋮</button>
                    <div class="block-summary"><span class="position"><?= $block['position'] ?></span><span class="block-symbol" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="5" rx="2"/><rect x="13" y="10" width="8" height="11" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/></svg></span><div><strong><?= $escape($block['name']) ?></strong><small><?= $escape($block['type']) ?></small></div></div>
                    <span class="block-visibility <?= $block['enabled'] ? 'is-visible' : '' ?>"><i aria-hidden="true"></i><?= $block['enabled'] ? 'Widoczny' : 'Ukryty' ?></span>
                    <div class="block-actions">
                        <a class="icon-button builder-action-edit" href="/admin/pages/builder/edit?path=<?= rawurlencode($identity->value()) ?>&amp;id=<?= rawurlencode($block['id']) ?>" aria-label="Edytuj blok" title="Edytuj blok"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4z"/></svg></a>
                        <?php foreach ($actions as [$url, $fixedLabel, $class]): $label = $fixedLabel ?? ($block['enabled'] ? 'Ukryj blok' : 'Pokaż blok'); ?>
                            <form method="post" action="<?= $url ?>"<?= $class === 'builder-action-remove' ? ' data-confirm="Usunąć ten blok bezpowrotnie?"' : '' ?>><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="identity" value="<?= $escape($identity->value()) ?>"><input type="hidden" name="id" value="<?= $escape($block['id']) ?>"><input type="hidden" name="revision" value="<?= $escape($revision->value()) ?>"><button type="submit" class="icon-button <?= $class ?>" aria-label="<?= $escape($label) ?>" title="<?= $escape($label) ?>">
                                <?php if ($class === 'builder-action-toggle'): ?><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?php if ($block['enabled']): ?><path d="M3 3l18 18M10.6 10.7a2 2 0 0 0 2.7 2.7M9.9 4.2A10.5 10.5 0 0 1 12 4c5 0 9 4 10 8a12.7 12.7 0 0 1-2.1 4.1M6.6 6.6A12.4 12.4 0 0 0 2 12c1 4 5 8 10 8 1.5 0 2.9-.4 4.1-1"/><?php else: ?><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><?php endif; ?></svg><?php elseif ($class === 'builder-action-duplicate'): ?><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg><?php else: ?><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2m-9 0 1 15h8l1-15M10 11v6m4-6v6"/></svg><?php endif; ?>
                            </button></form>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <a class="builder-inline-add" href="/admin/pages/builder/picker?path=<?= rawurlencode($identity->value()) ?>"><span aria-hidden="true">＋</span> Dodaj blok</a>
    </main>

    <aside class="block-editor-sidebar">
        <section><p class="eyebrow">Strona</p><h3>Ustawienia</h3><p>Zmień adres, metadane SEO, układ i widoczność strony.</p><a class="button secondary" href="/admin/pages/edit?path=<?= rawurlencode($identity->value()) ?>">Ustawienia strony</a></section>
        <section><p class="eyebrow">Zasoby</p><h3>Multimedia</h3><p>Zarządzaj obrazami i plikami używanymi przez tę stronę.</p><a class="button secondary" href="/admin/media?path=<?= rawurlencode($identity->value()) ?>">Biblioteka plików</a></section>
        <section class="editor-tip"><strong>Wskazówka</strong><p>Kliknij ołówek, aby edytować blok. Zmianę kolejności zatwierdź osobno.</p></section>
    </aside>
</div>

<form class="order-form editor-order-form" method="post" action="/admin/pages/builder/reorder" data-order-form><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="identity" value="<?= $escape($identity->value()) ?>"><input type="hidden" name="revision" value="<?= $escape($revision->value()) ?>"><span data-order-fields><?php foreach ($blocks as $block): ?><input type="hidden" name="order[]" value="<?= $escape($block['id']) ?>" data-order-field><?php endforeach; ?></span><span data-order-message>Kolejność bloków bez zmian</span><button type="submit" class="button" disabled data-order-submit>Zapisz kolejność</button></form>
