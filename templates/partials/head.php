<?php

declare(strict_types=1);

$seo = $data['seo'];
$assets = $data['assets'];
$robots = $seo['robots'];
$openGraph = $seo['openGraph'];
$twitter = $seo['twitter'];
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $context->escape($seo['title']) ?></title>
<meta name="description" content="<?= $context->escape($seo['description']) ?>">
<meta name="robots" content="<?= $robots['index'] ? 'index' : 'noindex' ?>,<?= $robots['follow'] ? 'follow' : 'nofollow' ?>">
<link rel="canonical" href="<?= $context->escape($seo['canonical']) ?>">
<meta property="og:title" content="<?= $context->escape($openGraph['title']) ?>">
<meta property="og:description" content="<?= $context->escape($openGraph['description']) ?>">
<meta property="og:url" content="<?= $context->escape($openGraph['url']) ?>">
<?php if (is_string($openGraph['image'] ?? null) && $openGraph['image'] !== ''): ?>
    <meta property="og:image" content="<?= $context->escape($openGraph['image']) ?>">
<?php endif; ?>
<meta name="twitter:title" content="<?= $context->escape($twitter['title']) ?>">
<meta name="twitter:description" content="<?= $context->escape($twitter['description']) ?>">
<?php if (is_string($twitter['image'] ?? null) && $twitter['image'] !== ''): ?>
    <meta name="twitter:image" content="<?= $context->escape($twitter['image']) ?>">
<?php endif; ?>
<?php if ($seo['jsonLd'] !== []): ?>
    <script type="application/ld+json"><?= json_encode($seo['jsonLd'], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
<link rel="stylesheet" href="/assets/css/site.css">
<?php foreach ($assets->styles() as $style): ?>
    <link rel="stylesheet" href="<?= $context->asset($style) ?>">
<?php endforeach; ?>
