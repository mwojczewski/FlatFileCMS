<?php

declare(strict_types=1);

$locale = $context->locale();
?>
<header class="article-hero">
    <div class="container article-hero__copy"><a href="/<?= $context->escape($locale) ?>/insights">← Insights</a>
        <p><span><?= $context->escape($data['category']) ?></span><time
                datetime="<?= $context->escape($data['date']) ?>"><?= $context->escape($data['date']) ?></time></p>
        <h1><?= $context->escape($data['title']) ?></h1>
        <div class="article-hero__lead"><?= $context->escape($data['lead']) ?></div>
    </div>
    <div class="container article-hero__image"><?= $context->picture(
        $data['image'],
        widths: [480, 768, 1024, 1280, 1600, 1920],
        format: 'webp',
        aspectRatio: 16 / 9,
        fit: 'cover',
        sizes: '(max-width: 1260px) calc(100vw - 2.5rem), 76rem',
        attributes: ['loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async'],
    ) ?></div>
</header>