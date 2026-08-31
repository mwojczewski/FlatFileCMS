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
                <figure><?= $context->image($item['image']) ?><?php if (($item['caption'] ?? '') !== ''): ?>
                        <figcaption><?= $context->escape($item['caption']) ?></figcaption><?php endif; ?>
                </figure><?php endforeach; ?>
        </div>
    </div>
</section>