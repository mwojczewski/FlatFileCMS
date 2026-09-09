<?php

declare(strict_types=1);

$seo = $data['seo'];
$assets = $data['assets'];
$robots = $seo['robots'];
$openGraph = $seo['openGraph'];
$twitter = $seo['twitter'];
$icons = is_array($seo['icons'] ?? null) ? $seo['icons'] : [];
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $context->escape($seo['title']) ?></title>
<meta name="description" content="<?= $context->escape($seo['description']) ?>">
<meta name="robots"
    content="<?= $robots['index'] ? 'index' : 'noindex' ?>,<?= $robots['follow'] ? 'follow' : 'nofollow' ?>">
<link rel="canonical" href="<?= $context->escape($seo['canonical']) ?>">
<?php if (is_string($icons['svg'] ?? null)): ?><link rel="icon" href="<?= $context->escape($icons['svg']) ?>" type="image/svg+xml"><?php endif; ?>
<?php if (is_string($icons['ico'] ?? null)): ?><link rel="icon" href="<?= $context->escape($icons['ico']) ?>" sizes="any"><?php endif; ?>
<?php if (is_string($icons['png32'] ?? null)): ?><link rel="icon" href="<?= $context->escape($icons['png32']) ?>" type="image/png" sizes="32x32"><?php endif; ?>
<?php if (is_string($icons['png16'] ?? null)): ?><link rel="icon" href="<?= $context->escape($icons['png16']) ?>" type="image/png" sizes="16x16"><?php endif; ?>
<?php if (is_string($icons['appleTouch'] ?? null)): ?><link rel="apple-touch-icon" href="<?= $context->escape($icons['appleTouch']) ?>" sizes="180x180"><?php endif; ?>
<?php if (is_string($icons['appleTouchPrecomposed'] ?? null)): ?><link rel="apple-touch-icon-precomposed" href="<?= $context->escape($icons['appleTouchPrecomposed']) ?>" sizes="180x180"><?php endif; ?>
<?php if (is_string($icons['manifest'] ?? null)): ?><link rel="manifest" href="<?= $context->escape($icons['manifest']) ?>"><?php endif; ?>
<meta property="og:title" content="<?= $context->escape($openGraph['title']) ?>">
<meta property="og:description" content="<?= $context->escape($openGraph['description']) ?>">
<meta property="og:url" content="<?= $context->escape($openGraph['url']) ?>">
<?php if (is_string($openGraph['type'] ?? null) && $openGraph['type'] !== ''): ?>
    <meta property="og:type" content="<?= $context->escape($openGraph['type']) ?>">
<?php endif; ?>
<?php if (is_string($openGraph['site_name'] ?? null) && $openGraph['site_name'] !== ''): ?>
    <meta property="og:site_name" content="<?= $context->escape($openGraph['site_name']) ?>">
<?php endif; ?>
<?php if (is_string($openGraph['locale'] ?? null) && $openGraph['locale'] !== ''): ?>
    <meta property="og:locale" content="<?= $context->escape($openGraph['locale']) ?>">
<?php endif; ?>
<?php if (is_string($openGraph['image'] ?? null) && $openGraph['image'] !== ''): ?>
    <meta property="og:image" content="<?= $context->escape($openGraph['image']) ?>">
<?php endif; ?>
<?php if (is_string($twitter['card'] ?? null) && $twitter['card'] !== ''): ?>
    <meta name="twitter:card" content="<?= $context->escape($twitter['card']) ?>">
<?php endif; ?>
<meta name="twitter:title" content="<?= $context->escape($twitter['title']) ?>">
<meta name="twitter:description" content="<?= $context->escape($twitter['description']) ?>">
<?php if (is_string($twitter['image'] ?? null) && $twitter['image'] !== ''): ?>
    <meta name="twitter:image" content="<?= $context->escape($twitter['image']) ?>">
<?php endif; ?>
<?php if ($seo['jsonLd'] !== []): ?>
    <script
        type="application/ld+json"><?= json_encode($seo['jsonLd'], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
<link rel="stylesheet" href="/assets/css/typography.css">
<link rel="stylesheet" href="/assets/css/site.css">
<?php foreach ($assets->styles() as $style): ?>
    <link rel="stylesheet" href="<?= $context->asset($style) ?>">
<?php endforeach; ?>
