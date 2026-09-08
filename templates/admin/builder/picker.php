<?php
$blockIcon = static function (string $type): string {
    $type = strtolower($type);
    if (str_contains($type, 'hero') || str_contains($type, 'image') || str_contains($type, 'media')) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m4 17 5-4 4 3 3-2 4 3"/></svg>';
    }
    if (str_contains($type, 'text') || str_contains($type, 'content') || str_contains($type, 'markdown')) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M5 5h14M5 9h14M5 13h9M5 17h12"/></svg>';
    }
    if (str_contains($type, 'contact') || str_contains($type, 'form')) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>';
    }
    if (str_contains($type, 'feature') || str_contains($type, 'grid') || str_contains($type, 'column')) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/></svg>';
    }
    if (str_contains($type, 'step') || str_contains($type, 'architecture') || str_contains($type, 'process')) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="12" cy="18" r="2.5"/><path d="m8 7.5 3 7.8m5-7.8-3 7.8M8.5 6h7"/></svg>';
    }

    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m12 2 8 4.5v9L12 20l-8-4.5v-9z"/><path d="m4.5 6.8 7.5 4.3 7.5-4.3M12 11v9"/></svg>';
};
?>
<div class="block-library-heading">
    <div><p class="eyebrow">Biblioteka bloków</p><h2>Co chcesz dodać?</h2><p class="lead">Wybierz komponent, który pojawi się na stronie <code><?= $escape($identity->value()) ?></code>.</p></div>
    <a class="button secondary" href="/admin/pages/builder?path=<?= rawurlencode($identity->value()) ?>">Anuluj</a>
</div>
<label class="pages-search block-search"><span class="sr-only">Szukaj bloku</span><svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input type="search" placeholder="Szukaj bloku…" data-block-search autocomplete="off"></label>
<div class="picker-grid block-library" data-block-library>
    <?php foreach ($cards as $card): $definition = $card['definition']; ?>
        <a class="picker-card" data-block-card data-block-search-value="<?= $escape(mb_strtolower($card['name'] . ' ' . $card['description'] . ' ' . $definition->type())) ?>" href="/admin/pages/builder/create?path=<?= rawurlencode($identity->value()) ?>&amp;type=<?= rawurlencode($definition->type()) ?>"><?php if ($card['preview']): ?><img src="/admin/pages/builder/preview?type=<?= rawurlencode($definition->type()) ?>" alt=""><?php else: ?><span class="block-icon" aria-hidden="true"><?= $blockIcon($definition->type()) ?></span><?php endif; ?><span><strong><?= $escape($card['name']) ?></strong><small><?= $escape($card['description']) ?></small><code><?= $escape($definition->type()) ?></code></span></a>
    <?php endforeach; ?>
</div>
<div class="empty-state block-library-empty" data-block-library-empty hidden><strong>Nie znaleziono takiego bloku.</strong><p>Spróbuj użyć innej nazwy.</p></div>
