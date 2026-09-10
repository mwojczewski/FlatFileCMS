<?php

declare(strict_types=1);

$queryUrl = static function (int $page) use ($collection, $filters): string {
    $query = [...$filters, 'page' => $page];

    return $collection['url'] . '?' . http_build_query($query, encoding_type: PHP_QUERY_RFC3986);
};

$documentLocale = $collection['locale'];
$documentBody = static function () use (
    $context,
    $navigation,
    $localizedUrls,
    $collection,
    $items,
    $pagination,
    $queryUrl,
): void {
    ?>
    <?= $context->partial('navigation', ['menus' => $navigation, 'localizedUrls' => $localizedUrls]) ?>
    <main id="main-content" class="collection container">
        <h1><?= $context->escape($collection['title']) ?></h1>
        <?php if ($items === []): ?>
            <p>No items found.</p>
        <?php else: ?>
            <div class="collection__items">
                <?php foreach ($items as $item): ?>
                    <article class="collection__item">
                        <h2><a href="<?= $context->escape($item['url']) ?>"><?= $context->escape($item['title']) ?></a></h2>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($pagination['totalPages'] > 1): ?>
            <nav class="pagination" aria-label="Pagination">
                <?php if ($pagination['page'] > 1): ?>
                    <a rel="prev" href="<?= $context->escape($queryUrl($pagination['page'] - 1)) ?>">Previous</a>
                <?php endif; ?>
                <span><?= $pagination['page'] ?> / <?= $pagination['totalPages'] ?></span>
                <?php if ($pagination['page'] < $pagination['totalPages']): ?>
                    <a rel="next" href="<?= $context->escape($queryUrl($pagination['page'] + 1)) ?>">Next</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </main>
    <?= $context->partial('footer', ['menus' => $navigation]) ?>
    <?php
};

require dirname(__DIR__) . '/document.php';
