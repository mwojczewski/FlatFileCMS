<?php

declare(strict_types=1);
?>
<section class="feature-grid" id="mozliwosci">
    <div class="container">
        <div class="section-heading">
            <div>
                <p class="section-kicker section-kicker--dark"><span></span><?= $context->escape($data['eyebrow']) ?>
                </p>
                <h2><?= $context->escape($data['title']) ?></h2>
            </div>
            <p><?= $context->escape($data['intro']) ?></p>
        </div>
        <div class="feature-grid__items">
            <?php foreach ($data['items'] as $item): ?>
                <article class="feature-card feature-card--<?= $context->escape($item['accent']) ?>">
                    <span class="feature-card__number"><?= $context->escape($item['number']) ?></span>
                    <div class="feature-card__icon" aria-hidden="true"></div>
                    <h3><?= $context->escape($item['title']) ?></h3>
                    <p><?= $context->escape($item['description']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>