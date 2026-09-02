<?php

declare(strict_types=1);

$locale = $context->locale();
?>
<section class="docs-navigation">
    <div class="container">
        <div class="docs-navigation__heading">
            <p>00 / <?= $locale === 'pl' ? 'Mapa tutorialu' : 'Tutorial map' ?></p>
            <div>
                <h2><?= $context->escape($data['title']) ?></h2>
                <p><?= $context->escape($data['intro']) ?></p>
            </div>
        </div>
        <nav aria-label="<?= $locale === 'pl' ? 'Spis treści' : 'Table of contents' ?>">
            <ol><?php foreach ($data['items'] as $item): ?>
                    <li><a href="<?= $context->url($item['url']) ?>"><span><?= $context->escape($item['number']) ?></span>
                            <div>
                                <strong><?= $context->escape($item['title']) ?></strong><small><?= $context->escape($item['description']) ?></small>
                            </div><i aria-hidden="true">↓</i>
                        </a></li><?php endforeach; ?>
            </ol>
        </nav>
        <div class="docs-navigation__matrix">
            <div class="matrix-row matrix-row--head">
                <span><?= $locale === 'pl' ? 'Warstwa' : 'Layer' ?></span><span><?= $locale === 'pl' ? 'Wejście' : 'Input' ?></span><span><?= $locale === 'pl' ? 'Odpowiedzialność' : 'Responsibility' ?></span><span><?= $locale === 'pl' ? 'Wyjście' : 'Output' ?></span>
            </div><?php foreach ($data['matrix'] as $row): ?>
                <div class="matrix-row">
                    <strong><?= $context->escape($row['layer']) ?></strong><span><?= $context->escape($row['input']) ?></span><span><?= $context->escape($row['responsibility']) ?></span><span><?= $context->escape($row['output']) ?></span>
                </div><?php endforeach; ?>
        </div>
    </div>
</section>