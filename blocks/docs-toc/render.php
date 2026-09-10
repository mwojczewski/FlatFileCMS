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
                            <i aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                    class="bi bi-arrow-down-right-square" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd"
                                        d="M15 2a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1zM0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm5.854 3.146a.5.5 0 1 0-.708.708L9.243 9.95H6.475a.5.5 0 1 0 0 1h3.975a.5.5 0 0 0 .5-.5V6.475a.5.5 0 1 0-1 0v2.768z" />
                                </svg>
                            </i>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
    </div>
</section>