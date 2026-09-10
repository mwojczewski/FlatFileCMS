<?php

declare(strict_types=1);

$documentLocale = $page->locale();
$documentBody = static function () use ($context, $page, $navigation, $content): void {
    ?>
    <?= $context->partial('navigation', ['menus' => $navigation, 'localizedUrls' => $page->localizedUrls()]) ?>
    <main id="main-content">
        <?= $content ?>
    </main>
    <?= $context->partial('footer', ['menus' => $navigation]) ?>
    <?php
};

require dirname(__DIR__) . '/document.php';
