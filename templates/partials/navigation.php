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
            <nav class="site-nav" id="site-navigation" aria-label="<?= $locale === 'pl' ? 'Główna nawigacja' : 'Main navigation' ?>">
                <ul><?php foreach ($main as $item): ?><?php $target = $item['target'] === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>
                        <li><a href="<?= $context->escape($localizedUrl($item['url'])) ?>" <?= $target ?>><?= $context->escape($item['label']) ?></a></li><?php endforeach; ?>
                </ul>
            </nav>
        <?php endif; ?>
        <div class="site-header__tools"><button class="menu-toggle" type="button" aria-controls="site-navigation"
                aria-expanded="false" aria-label="<?= $locale === 'pl' ? 'Otwórz menu' : 'Open menu' ?>"
                data-open-label="<?= $locale === 'pl' ? 'Otwórz menu' : 'Open menu' ?>"
                data-close-label="<?= $locale === 'pl' ? 'Zamknij menu' : 'Close menu' ?>"><span></span><span></span></button><a class="language-switch" href="/<?= $context->escape($otherLocale) ?>/"
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

    .menu-toggle {
        display: none;
        width: 2.75rem;
        height: 2.75rem;
        padding: 0;
        border: 1px solid #365149;
        border-radius: .2rem;
        background: transparent;
        color: inherit;
        cursor: pointer
    }

    .menu-toggle span {
        position: absolute;
        width: 1rem;
        height: 1px;
        background: currentColor;
        transition: transform .2s ease
    }

    .menu-toggle span:first-child {
        transform: translateY(-.22rem)
    }

    .menu-toggle span:last-child {
        transform: translateY(.22rem)
    }

    .menu-toggle[aria-expanded="true"] span:first-child {
        transform: rotate(45deg)
    }

    .menu-toggle[aria-expanded="true"] span:last-child {
        transform: rotate(-45deg)
    }

    @media(max-width:760px) {
        .site-header:has(.menu-toggle[aria-expanded="true"]) {
            background: #07110f
        }

        .site-header__inner {
            position: relative
        }

        .menu-toggle {
            display: grid;
            place-items: center
        }

        .site-nav {
            position: absolute;
            top: 100%;
            left: -1.25rem;
            display: block;
            width: calc(100% + 2.5rem);
            max-height: 0;
            overflow: hidden;
            background: #07110f;
            opacity: 0;
            visibility: hidden;
            transition: max-height .3s ease, opacity .2s ease, visibility .2s
        }

        .site-nav.is-open {
            max-height: 28rem;
            border-top: 1px solid #1d3029;
            opacity: 1;
            visibility: visible
        }

        .site-nav>ul {
            display: grid;
            gap: 0;
            padding: .75rem 1.25rem 1.5rem
        }

        .site-nav li {
            border-bottom: 1px solid #1d3029
        }

        .site-nav li:last-child {
            border-bottom: 0
        }

        .site-nav a {
            display: flex;
            min-height: 3.5rem;
            align-items: center;
            justify-content: space-between;
            color: #dce8e3;
            font-size: .9rem
        }

        .site-nav a::after {
            content: '→';
            color: #59e4ae
        }

        .site-header__admin {
            display: none
        }
    }
</style>
<script src="/assets/js/site-navigation.js" defer></script>
