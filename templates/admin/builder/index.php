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
        <div>
            <p class="eyebrow">Edytor strony</p>
            <h2><?= $escape($identity->isHomepage() ? 'Strona główna' : $identity->value()) ?></h2>
        </div>
        <span class="editor-state"><i aria-hidden="true"></i><?= $enabledCount ?> z <?= count($blocks) ?>
            aktywnych</span>
    </div>
    <div class="actions editor-primary-actions">
        <span class="editor-live-state" data-editor-live-state>Podgląd aktualny</span>
        <a class="button secondary" href="<?= $escape($previewUrl) ?>" target="_blank" rel="noopener">Podgląd <span
                aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                    class="bi bi-arrow-up-right-circle" viewBox="0 0 16 16">
                    <path fill-rule="evenodd"
                        d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8m15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.854 10.803a.5.5 0 1 1-.708-.707L9.243 6H6.475a.5.5 0 1 1 0-1h3.975a.5.5 0 0 1 .5.5v3.975a.5.5 0 1 1-1 0V6.707z" />
                </svg>
            </span>
        </a>
        <a class="button" href="/admin/pages/builder/picker?path=<?= rawurlencode($identity->value()) ?>">
            <span aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                    class="bi bi-plus-circle" viewBox="0 0 16 16">
                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16" />
                    <path
                        d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4" />
                </svg>
            </span> Dodaj blok
        </a>
    </div>
</div>

<div class="block-editor-layout block-editor-layout-live">
    <main class="block-editor-canvas block-editor-canvas-live">
        <header class="canvas-heading">
            <div><span class="canvas-dot" aria-hidden="true"></span><strong>Podgląd strony</strong></div><small>Kliknij
                blok, aby edytować jego treść</small>
        </header>
        <div class="builder-list builder-preview-list" data-builder-list>
            <?php if ($blocks === []): ?>
                <div class="empty-state builder-empty"><span aria-hidden="true">＋</span><strong>Rozpocznij budowę
                        strony</strong>
                    <p>Dodaj pierwszy blok z biblioteki komponentów.</p><a class="button"
                        href="/admin/pages/builder/picker?path=<?= rawurlencode($identity->value()) ?>">Wybierz blok</a>
                </div>
            <?php endif; ?>
            <?php foreach ($blocks as $index => $block): ?>
                <article
                    class="builder-item builder-preview-item<?= $block['enabled'] ? '' : ' disabled' ?><?= $index === 0 ? ' selected' : '' ?>"
                    draggable="true" data-block-id="<?= $escape($block['id']) ?>"
                    data-block-select="<?= $escape($block['id']) ?>">
                    <header class="builder-preview-toolbar">
                        <button type="button" class="drag-handle" aria-label="Przeciągnij blok" title="Zmień kolejność">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                class="bi bi-grip-vertical" viewBox="0 0 16 16">
                                <path
                                    d="M7 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0m3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0M7 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0m3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0M7 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0m3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0m-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0m3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0m-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0m3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0" />
                            </svg>
                        </button>
                        <span class="builder-preview-type"><?= $escape($block['name']) ?></span>
                        <span class="block-visibility <?= $block['enabled'] ? 'is-visible' : '' ?>"><i
                                aria-hidden="true"></i><?= $block['enabled'] ? 'Widoczny' : 'Ukryty' ?></span>
                        <div class="block-actions">
                            <button type="button" class="icon-button builder-action-move" data-block-move="-1"
                                aria-label="Przenieś blok wyżej" title="Przenieś wyżej">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                    class="bi bi-arrow-up-short" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd"
                                        d="M8 12a.5.5 0 0 0 .5-.5V5.707l2.146 2.147a.5.5 0 0 0 .708-.708l-3-3a.5.5 0 0 0-.708 0l-3 3a.5.5 0 1 0 .708.708L7.5 5.707V11.5a.5.5 0 0 0 .5.5" />
                                </svg>
                            </button>
                            <button type="button" class="icon-button builder-action-move" data-block-move="1"
                                aria-label="Przenieś blok niżej" title="Przenieś niżej">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                    class="bi bi-arrow-down-short" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd"
                                        d="M8 4a.5.5 0 0 1 .5.5v5.793l2.146-2.147a.5.5 0 0 1 .708.708l-3 3a.5.5 0 0 1-.708 0l-3-3a.5.5 0 1 1 .708-.708L7.5 10.293V4.5A.5.5 0 0 1 8 4" />
                                </svg>
                            </button>
                            <button type="button" class="icon-button builder-action-edit"
                                data-block-edit="<?= $escape($block['id']) ?>" aria-label="Edytuj blok"
                                title="Edytuj blok"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                    fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
                                    <path
                                        d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z" />
                                    <path fill-rule="evenodd"
                                        d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z" />
                                </svg>
                            </button>
                            <?php foreach ($actions as [$url, $fixedLabel, $class]):
                                $label = $fixedLabel ?? ($block['enabled'] ? 'Ukryj blok' : 'Pokaż blok'); ?>
                                <form method="post" action="<?= $url ?>" <?= $class === 'builder-action-remove' ? ' data-confirm="Usunąć ten blok bezpowrotnie?"' : '' ?>>
                                    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="identity" value="<?= $escape($identity->value()) ?>">
                                    <input type="hidden" name="id" value="<?= $escape($block['id']) ?>">
                                    <input type="hidden" name="revision" value="<?= $escape($revision->value()) ?>">
                                    <button type="submit" class="icon-button <?= $class ?>" aria-label="<?= $escape($label) ?>"
                                        title="<?= $escape($label) ?>">
                                        <?php if ($class === 'builder-action-toggle'): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                                class="bi bi-eye" viewBox="0 0 16 16">

                                                <?php if ($block['enabled']): ?>
                                                    <path
                                                        d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7 7 0 0 0-2.79.588l.77.771A6 6 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755q-.247.248-.517.486z" />
                                                    <path
                                                        d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829" />
                                                    <path
                                                        d="M3.35 5.47q-.27.24-.518.487A13 13 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7 7 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12z" />
                                                <?php else: ?>
                                                    <path
                                                        d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z" />
                                                    <path
                                                        d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0" />
                                                <?php endif; ?>
                                            </svg>
                                        <?php elseif ($class === 'builder-action-duplicate'): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                                class="bi bi-copy" viewBox="0 0 16 16">
                                                <path fill-rule="evenodd"
                                                    d="M4 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zM2 5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1h1v1a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1v1z" />
                                            </svg>
                                        <?php else: ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                                class="bi bi-trash3" viewBox="0 0 16 16">
                                                <path
                                                    d="M6.5 1h3a.5.5 0 0 1 .5.5v1H6v-1a.5.5 0 0 1 .5-.5M11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3A1.5 1.5 0 0 0 5 1.5v1H1.5a.5.5 0 0 0 0 1h.538l.853 10.66A2 2 0 0 0 4.885 16h6.23a2 2 0 0 0 1.994-1.84l.853-10.66h.538a.5.5 0 0 0 0-1zm1.958 1-.846 10.58a1 1 0 0 1-.997.92h-6.23a1 1 0 0 1-.997-.92L3.042 3.5zm-7.487 1a.5.5 0 0 1 .528.47l.5 8.5a.5.5 0 0 1-.998.06L5 5.03a.5.5 0 0 1 .47-.53Zm5.058 0a.5.5 0 0 1 .47.53l-.5 8.5a.5.5 0 1 1-.998-.06l.5-8.5a.5.5 0 0 1 .528-.47M8 4.5a.5.5 0 0 1 .5.5v8.5a.5.5 0 0 1-1 0V5a.5.5 0 0 1 .5-.5" />
                                            </svg>
                                        <?php endif; ?>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </header>
                    <div class="builder-frame-stage" data-preview-stage>
                        <iframe class="builder-block-frame" data-preview-frame="<?= $escape($block['id']) ?>"
                            title="Podgląd: <?= $escape($block['name']) ?>" srcdoc="<?= $escape($block['preview']) ?>"
                            sandbox="allow-same-origin" loading="lazy" tabindex="-1"></iframe>
                        <button type="button" class="builder-preview-shield" data-block-edit="<?= $escape($block['id']) ?>"
                            aria-label="Edytuj blok: <?= $escape($block['name']) ?>"></button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <a class="builder-inline-add"
            href="/admin/pages/builder/picker?path=<?= rawurlencode($identity->value()) ?>"><span
                aria-hidden="true">＋</span> Dodaj blok</a>
    </main>

    <aside class="block-editor-inspector" data-block-inspector>
        <header class="block-inspector-heading">
            <div>
                <p class="eyebrow">Ustawienia bloku</p>
                <h3 data-inspector-title><?= $blocks === [] ? 'Wybierz blok' : $escape($blocks[0]['name']) ?></h3>
            </div>
            <div class="block-inspector-heading-actions"><button type="button"
                    class="icon-button block-inspector-expand" data-inspector-expand aria-label="Rozszerz panel edycji"
                    title="Rozszerz panel edycji"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2">
                        <path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5" />
                    </svg></button><button type="button" class="dialog-close" data-inspector-close
                    aria-label="Zamknij ustawienia">×</button></div>
        </header>
        <?php if ($blocks === []): ?>
            <div class="block-inspector-empty">
                <p>Kliknij blok w podglądzie, aby edytować jego zawartość.</p>
            </div><?php endif; ?>
        <?php foreach ($blocks as $index => $block): ?>
            <form class="block-inspector-form" method="post" action="/admin/pages/builder/update"
                data-page-identity="<?= $escape($identity->value()) ?>" data-block-form="<?= $escape($block['id']) ?>"
                data-block-name="<?= $escape($block['name']) ?>" <?= $index === 0 ? '' : ' hidden' ?>>
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="identity" value="<?= $escape($identity->value()) ?>">
                <input type="hidden" name="type" value="<?= $escape($block['type']) ?>">
                <input type="hidden" name="id" value="<?= $escape($block['id']) ?>">
                <input type="hidden" name="revision" value="<?= $escape($revision->value()) ?>">
                <nav class="block-inspector-tabs" aria-label="Sekcje ustawień" role="tablist">
                    <button type="button" class="active" data-inspector-tab="content" role="tab"
                        aria-selected="true">Treść</button>
                    <button type="button" data-inspector-tab="appearance" role="tab" aria-selected="false">Wygląd</button>
                    <button type="button" data-inspector-tab="visibility" role="tab"
                        aria-selected="false">Widoczność</button>
                </nav>
                <?= $block['fields'] ?>
                <div class="block-inspector-fields block-panel-fields block-visibility-panel"
                    data-block-panel-fields="visibility" hidden>
                    <div class="field">
                        <div class="field-heading"><span>Widoczność bloku</span><small>Ukryty blok pozostaje zapisany w
                                treści strony, ale nie jest renderowany publicznie.</small></div>
                        <label class="switch"><input type="checkbox" data-block-visibility<?= $block['enabled'] ? ' checked' : '' ?>><span
                                data-block-visibility-label><?= $block['enabled'] ? 'Blok widoczny' : 'Blok ukryty' ?></span></label>
                    </div>
                </div>
                <footer class="block-inspector-actions"><span data-block-preview-status>Podgląd aktualny</span><button
                        type="submit">Zapisz blok</button></footer>
            </form>
        <?php endforeach; ?>
        <div class="block-inspector-page-links">
            <a href="/admin/pages/edit?path=<?= rawurlencode($identity->value()) ?>">Ustawienia strony <span
                    aria-hidden="true">→</span></a>
            <a href="/admin/media?path=<?= rawurlencode($identity->value()) ?>">Biblioteka plików <span
                    aria-hidden="true">→</span></a>
        </div>
    </aside>
</div>

<form class="order-form editor-order-form" method="post" action="/admin/pages/builder/reorder" data-order-form><input
        type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="identity"
        value="<?= $escape($identity->value()) ?>"><input type="hidden" name="revision"
        value="<?= $escape($revision->value()) ?>"><span data-order-fields><?php foreach ($blocks as $block): ?><input
                type="hidden" name="order[]" value="<?= $escape($block['id']) ?>"
                data-order-field><?php endforeach; ?></span><span data-order-message>Kolejność bloków bez
        zmian</span><button type="submit" class="button" disabled data-order-submit>Zapisz kolejność</button></form>