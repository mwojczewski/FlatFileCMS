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
            <ol>
                <?php foreach ($data['items'] as $item): ?>
                    <li>
                        <a href="<?= $context->url($item['url']) ?>">
                            <span><?= $context->escape($item['number']) ?></span>
                            <div>
                                <strong><?= $context->escape($item['title']) ?></strong>
                                <small><?= $context->escape($item['description']) ?></small>
                            </div>
                            <i aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                    class="bi bi-arrow-down" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd"
                                        d="M8 1a.5.5 0 0 1 .5.5v11.793l3.146-3.147a.5.5 0 0 1 .708.708l-4 4a.5.5 0 0 1-.708 0l-4-4a.5.5 0 0 1 .708-.708L7.5 13.293V1.5A.5.5 0 0 1 8 1" />
                                </svg>
                            </i>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
        <div class="docs-navigation__matrix">
            <div class="matrix-row matrix-row--head">
                <span><?= $locale === 'pl' ? 'Warstwa' : 'Layer' ?></span>
                <span><?= $locale === 'pl' ? 'Wejście' : 'Input' ?></span>
                <span><?= $locale === 'pl' ? 'Odpowiedzialność' : 'Responsibility' ?></span>
                <span><?= $locale === 'pl' ? 'Wyjście' : 'Output' ?></span>
            </div>
            <?php foreach ($data['matrix'] as $row): ?>
                <div class="matrix-row">
                    <strong><?= $context->escape($row['layer']) ?></strong>
                    <span><?= $context->escape($row['input']) ?></span>
                    <span><?= $context->escape($row['responsibility']) ?></span>
                    <span><?= $context->escape($row['output']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>