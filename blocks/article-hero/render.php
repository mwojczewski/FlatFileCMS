<?php

declare(strict_types=1);

$locale = $context->locale();
?>
<header class="article-hero">
    <div class="container article-hero__copy">
        <a href="/<?= $context->escape($locale) ?>/insights">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left"
                viewBox="0 0 16 16">
                <path fill-rule="evenodd"
                    d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8" />
            </svg>
            &nbsp;Insights

        </a>
        <p>
            <span><?= $context->escape($data['category']) ?></span>
            <time datetime="<?= $context->escape($data['date']) ?>"><?= $context->escape($data['date']) ?></time>
        </p>
        <h1><?= $context->escape($data['title']) ?></h1>
        <div class="article-hero__lead"><?= $context->escape($data['lead']) ?></div>
    </div>
    <div class="container article-hero__image">
        <?= $context->picture(
            $data['image'],
            widths: [480, 768, 1024, 1280, 1600, 1920],
            format: 'webp',
            aspectRatio: 16 / 9,
            fit: 'cover',
            sizes: '(max-width: 1260px) calc(100vw - 2.5rem), 76rem',
            attributes: ['loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async'],
        ) ?>
    </div>
</header>