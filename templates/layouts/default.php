<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="<?= $context->escape($page->locale()) ?>">

<head>
    <?= $context->partial('head', ['page' => $page, 'seo' => $seo, 'assets' => $assets]) ?>
</head>

<body>
    <?= $context->partial('navigation', ['menus' => $navigation]) ?>
    <main id="main-content">
        <?= $content ?>
    </main>
    <?= $context->partial('footer', ['menus' => $navigation]) ?>
    <?php foreach ($assets->scripts() as $script): ?>
        <script src="<?= $context->asset($script) ?>" defer></script>
    <?php endforeach; ?>
</body>

</html>