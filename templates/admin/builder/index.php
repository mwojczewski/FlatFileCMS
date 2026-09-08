<?php
$actions = [
    ['/admin/pages/builder/toggle', null, 'builder-action-toggle'],
    ['/admin/pages/builder/duplicate', 'Duplikuj blok', 'builder-action-duplicate'],
    ['/admin/pages/builder/delete', 'Usuń blok', 'builder-action-remove'],
];
$enabledCount = count(array_filter($blocks, static fn(array $block): bool => $block['enabled']));
?>
<div class="editor-topbar wordpress-editor-topbar">
    <div class="editor-page-identity">
        <a class="editor-back" href="/admin/pages" aria-label="Wróć do stron">←</a>
        <div><p class="eyebrow">Edytor strony</p><h2><?= $escape($identity->isHomepage() ? 'Strona główna' : $identity->value()) ?></h2></div>
        <span class="editor-state"><i aria-hidden="true"></i><?= $enabledCount ?> z <?= count($blocks) ?> aktywnych</span>
    </div>
    <div class="actions editor-primary-actions">
        <span class="editor-live-state" data-editor-live-state>Podgląd aktualny</span>
        <a class="button secondary" href="<?= $escape($previewUrl) ?>" target="_blank" rel="noopener">Podgląd <span aria-hidden="true">↗</span></a>
        <a class="button" href="/admin/pages/builder/picker?path=<?= rawurlencode($identity->value()) ?>"><span aria-hidden="true">＋</span> Dodaj blok</a>
    </div>
</div>

<div class="block-editor-layout block-editor-layout-live">
    <main class="block-editor-canvas block-editor-canvas-live">
        <header class="canvas-heading"><div><span class="canvas-dot" aria-hidden="true"></span><strong>Podgląd strony</strong></div><small>Kliknij blok, aby edytować jego treść</small></header>
        <div class="builder-list builder-preview-list" data-builder-list>
            <?php if ($blocks === []): ?>
                <div class="empty-state builder-empty"><span aria-hidden="true">＋</span><strong>Rozpocznij budowę strony</strong><p>Dodaj pierwszy blok z biblioteki komponentów.</p><a class="button" href="/admin/pages/builder/picker?path=<?= rawurlencode($identity->value()) ?>">Wybierz blok</a></div>
            <?php endif; ?>
            <?php foreach ($blocks as $index => $block): ?>
                <article class="builder-item builder-preview-item<?= $block['enabled'] ? '' : ' disabled' ?><?= $index === 0 ? ' selected' : '' ?>" draggable="true" data-block-id="<?= $escape($block['id']) ?>" data-block-select="<?= $escape($block['id']) ?>">
                    <header class="builder-preview-toolbar">
                        <button type="button" class="drag-handle" aria-label="Przeciągnij blok" title="Zmień kolejność">⋮⋮</button>
                        <span class="builder-preview-type"><?= $escape($block['name']) ?></span>
                        <span class="block-visibility <?= $block['enabled'] ? 'is-visible' : '' ?>"><i aria-hidden="true"></i><?= $block['enabled'] ? 'Widoczny' : 'Ukryty' ?></span>
                        <div class="block-actions">
                            <button type="button" class="icon-button builder-action-edit" data-block-edit="<?= $escape($block['id']) ?>" aria-label="Edytuj blok" title="Edytuj blok"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4z"/></svg></button>
                            <?php foreach ($actions as [$url, $fixedLabel, $class]): $label = $fixedLabel ?? ($block['enabled'] ? 'Ukryj blok' : 'Pokaż blok'); ?>
                                <form method="post" action="<?= $url ?>"<?= $class === 'builder-action-remove' ? ' data-confirm="Usunąć ten blok bezpowrotnie?"' : '' ?>><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="identity" value="<?= $escape($identity->value()) ?>"><input type="hidden" name="id" value="<?= $escape($block['id']) ?>"><input type="hidden" name="revision" value="<?= $escape($revision->value()) ?>"><button type="submit" class="icon-button <?= $class ?>" aria-label="<?= $escape($label) ?>" title="<?= $escape($label) ?>">
                                    <?php if ($class === 'builder-action-toggle'): ?><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?php if ($block['enabled']): ?><path d="M3 3l18 18M10.6 10.7a2 2 0 0 0 2.7 2.7M9.9 4.2A10.5 10.5 0 0 1 12 4c5 0 9 4 10 8a12.7 12.7 0 0 1-2.1 4.1M6.6 6.6A12.4 12.4 0 0 0 2 12c1 4 5 8 10 8 1.5 0 2.9-.4 4.1-1"/><?php else: ?><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><?php endif; ?></svg><?php elseif ($class === 'builder-action-duplicate'): ?><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg><?php else: ?><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2m-9 0 1 15h8l1-15M10 11v6m4-6v6"/></svg><?php endif; ?>
                                </button></form>
                            <?php endforeach; ?>
                        </div>
                    </header>
                    <div class="builder-frame-stage" data-preview-stage>
                        <iframe class="builder-block-frame" data-preview-frame="<?= $escape($block['id']) ?>" title="Podgląd: <?= $escape($block['name']) ?>" srcdoc="<?= $escape($block['preview']) ?>" sandbox="allow-same-origin" loading="lazy" tabindex="-1"></iframe>
                        <button type="button" class="builder-preview-shield" data-block-edit="<?= $escape($block['id']) ?>" aria-label="Edytuj blok: <?= $escape($block['name']) ?>"></button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <a class="builder-inline-add" href="/admin/pages/builder/picker?path=<?= rawurlencode($identity->value()) ?>"><span aria-hidden="true">＋</span> Dodaj blok</a>
    </main>

    <aside class="block-editor-inspector" data-block-inspector>
        <header class="block-inspector-heading"><div><p class="eyebrow">Ustawienia bloku</p><h3 data-inspector-title><?= $blocks === [] ? 'Wybierz blok' : $escape($blocks[0]['name']) ?></h3></div><div class="block-inspector-heading-actions"><button type="button" class="icon-button block-inspector-expand" data-inspector-expand aria-label="Rozszerz panel edycji" title="Rozszerz panel edycji"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5"/></svg></button><button type="button" class="dialog-close" data-inspector-close aria-label="Zamknij ustawienia">×</button></div></header>
        <?php if ($blocks === []): ?><div class="block-inspector-empty"><p>Kliknij blok w podglądzie, aby edytować jego zawartość.</p></div><?php endif; ?>
        <?php foreach ($blocks as $index => $block): ?>
            <form class="block-inspector-form" method="post" action="/admin/pages/builder/update" data-page-identity="<?= $escape($identity->value()) ?>" data-block-form="<?= $escape($block['id']) ?>" data-block-name="<?= $escape($block['name']) ?>"<?= $index === 0 ? '' : ' hidden' ?>>
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="identity" value="<?= $escape($identity->value()) ?>">
                <input type="hidden" name="type" value="<?= $escape($block['type']) ?>">
                <input type="hidden" name="id" value="<?= $escape($block['id']) ?>">
                <input type="hidden" name="revision" value="<?= $escape($revision->value()) ?>">
                <nav class="block-inspector-tabs" aria-label="Sekcje ustawień"><span class="active">Treść</span><span>Wygląd</span><span>Widoczność</span></nav>
                <div class="block-inspector-fields"><?= $block['fields'] ?></div>
                <footer class="block-inspector-actions"><span data-block-preview-status>Podgląd aktualny</span><button type="submit">Zapisz blok</button></footer>
            </form>
        <?php endforeach; ?>
        <div class="block-inspector-page-links">
            <a href="/admin/pages/edit?path=<?= rawurlencode($identity->value()) ?>">Ustawienia strony <span aria-hidden="true">→</span></a>
            <a href="/admin/media?path=<?= rawurlencode($identity->value()) ?>">Biblioteka plików <span aria-hidden="true">→</span></a>
        </div>
    </aside>
</div>

<form class="order-form editor-order-form" method="post" action="/admin/pages/builder/reorder" data-order-form><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="identity" value="<?= $escape($identity->value()) ?>"><input type="hidden" name="revision" value="<?= $escape($revision->value()) ?>"><span data-order-fields><?php foreach ($blocks as $block): ?><input type="hidden" name="order[]" value="<?= $escape($block['id']) ?>" data-order-field><?php endforeach; ?></span><span data-order-message>Kolejność bloków bez zmian</span><button type="submit" class="button" disabled data-order-submit>Zapisz kolejność</button></form>
