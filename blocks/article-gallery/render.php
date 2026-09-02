<?php

declare(strict_types=1);

$locale = $context->locale();
?>
<section class="article-gallery">
    <div class="container">
        <div class="article-gallery__heading">
            <p><?= $locale === 'pl' ? 'Studium wizualne' : 'Visual study' ?></p>
            <h2><?= $context->escape($data['title']) ?></h2>
        </div>
        <div class="article-gallery__grid"><?php foreach ($data['images'] as $item): ?>
                <figure><?= $context->picture(
                    $item['image'],
                    widths: [320, 480, 640, 768, 960, 1200],
                    format: 'webp',
                    aspectRatio: 16 / 9,
                    fit: 'cover',
                    sizes: '(max-width: 650px) calc(100vw - 2.5rem), (max-width: 1260px) calc((100vw - 3.5rem) / 2), 37.5rem',
                    attributes: ['loading' => 'lazy', 'decoding' => 'async'],
                ) ?><?php if (($item['caption'] ?? '') !== ''): ?>
                        <figcaption><?= $context->escape($item['caption']) ?></figcaption><?php endif; ?>
                </figure><?php endforeach; ?>
        </div>
    </div>
</section>