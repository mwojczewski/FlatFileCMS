<?php

declare(strict_types=1);

$locale = $context->locale();
?>
<section class="docs-toc">
    <div class="container">
        <header class="docs-toc__heading">
            <p>00 / <?= $locale === 'pl' ? 'Mapa tutorialu' : 'Tutorial map' ?></p>
            <div>
                <h2><?= $context->escape($data['title']) ?></h2>
                <p><?= $context->escape($data['intro']) ?></p>
            </div>
        </header>
        <nav aria-label="<?= $locale === 'pl' ? 'Spis treści' : 'Table of contents' ?>">
            <ol>
                <?php foreach ($data['items'] as $item): ?>
                    <li>
                        <a href="<?= $context->url($item['url']) ?>">
                            <span><?= $context->escape($item['number']) ?></span>
                            <div>
                                <strong><?= $context->escape($item['title']) ?></strong>
                                <small><?= $context->escape($item['description']) ?></small>
                            </div>
                            <i aria-hidden="true">↓</i>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
    </div>
</section>
