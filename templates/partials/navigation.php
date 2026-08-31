<?php

declare(strict_types=1);

$menus = $data['menus'];
$main = $menus['main'] ?? [];
$locale = $context->locale();
$otherLocale = $locale === 'pl' ? 'en' : 'pl';
$localizedUrl = static function (string $url) use ($locale): string {
    if ($url === '/' || str_starts_with($url, '/#')) {
        return "/{$locale}/" . ltrim($url, '/');
    }

    return $url;
};
?>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="site-brand" href="/<?= $context->escape($locale) ?>/"><span class="site-brand__mark"
                aria-hidden="true"><i></i><i></i><i></i><i></i></span>FlatFile CMS</a>
        <?php if ($main !== []): ?>
            <nav class="site-nav" aria-label="<?= $locale === 'pl' ? 'Główna nawigacja' : 'Main navigation' ?>">
                <ul><?php foreach ($main as $item): ?><?php $target = $item['target'] === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>
                        <li><a href="<?= $context->escape($localizedUrl($item['url'])) ?>" <?= $target ?>><?= $context->escape($item['label']) ?></a></li><?php endforeach; ?>
                </ul>
            </nav>
        <?php endif; ?>
        <div class="site-header__tools"><a class="language-switch" href="/<?= $context->escape($otherLocale) ?>/"
                hreflang="<?= $context->escape($otherLocale) ?>" lang="<?= $context->escape($otherLocale) ?>"
                aria-label="<?= $locale === 'pl' ? 'Switch to English' : 'Przełącz na język polski' ?>"><span
                    class="<?= $locale === 'pl' ? 'is-active' : '' ?>">PL</span><i>/</i><span
                    class="<?= $locale === 'en' ? 'is-active' : '' ?>">EN</span></a><a class="site-header__admin"
                href="/admin"><?= $locale === 'pl' ? 'Panel CMS' : 'CMS panel' ?> <span aria-hidden="true">↗</span></a>
        </div>
    </div>
</header>
<style>
    .site-header__tools {
        display: flex;
        align-items: center;
        gap: 1rem
    }

    .language-switch {
        display: flex;
        gap: .35rem;
        color: #73877f;
        font: 700 .66rem ui-monospace, monospace;
        text-decoration: none
    }

    .language-switch i {
        color: #3a4d46;
        font-style: normal
    }

    .language-switch .is-active {
        color: #65e4b3
    }

    @media(max-width:560px) {
        .site-header__admin {
            display: none
        }
    }
</style>