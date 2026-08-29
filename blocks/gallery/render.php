<?php

declare(strict_types=1);

$images = $data['images'];
?>
<section class="gallery">
    <div class="container gallery__grid">
        <?php foreach ($images as $item): ?>
            <figure class="gallery__item">
                <?= $context->image($item['image']) ?>
                <?php if (($item['caption'] ?? '') !== ''): ?>
                    <figcaption><?= $context->escape($item['caption']) ?></figcaption>
                <?php endif; ?>
            </figure>
        <?php endforeach; ?>
    </div>
</section>