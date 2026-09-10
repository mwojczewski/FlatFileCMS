<?php

declare(strict_types=1);

$documentHead ??= null;
$documentBodyClass ??= '';
?>
<!doctype html>
<html lang="<?= $context->escape($documentLocale) ?>">

<head>
    <?= $context->partial('head', ['seo' => $seo, 'assets' => $assets]) ?>
    <?php if ($documentHead !== null): ?>
        <?php $documentHead(); ?>
    <?php endif; ?>
</head>

<body<?= $documentBodyClass === '' ? '' : ' class="' . $context->escape($documentBodyClass) . '"' ?>>
    <?php $documentBody(); ?>
    <?php foreach ($assets->scripts() as $script): ?>
        <script src="<?= $context->asset($script) ?>" defer></script>
    <?php endforeach; ?>
    <?= $context->cloudflareBeacon() ?>
</body>

</html>
