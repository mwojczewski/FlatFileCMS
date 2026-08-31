<?php
$links = [
    ['/admin', 'dashboard', 'Pulpit', 'Przegląd systemu'],
    ['/admin/pages', 'pages', 'Strony', 'Treść i page builder'],
    ['/admin/navigation', 'navigation', 'Nawigacja', 'Menu i hierarchia linków'],
    ['/admin/redirects', 'redirects', 'Przekierowania', 'Reguły adresów 3xx'],
    ['/admin/settings', 'settings', 'Konfiguracja', 'SEO, witryna i multimedia'],
    ['/admin/users', 'users', 'Administratorzy', 'Konta panelu'],
    ['/admin/security', 'account', 'Konto', 'Hasło i zabezpieczenia'],
];
?>
<a class="skip-link" href="#admin-content">Przejdź do treści</a>
<div class="admin-shell">
    <div class="admin-backdrop" data-admin-backdrop></div>
    <aside class="admin-sidebar" data-admin-sidebar>
        <div class="admin-brand">
            <a href="/admin" aria-label="FlatFile CMS — panel">
                <span class="admin-brand-mark">F</span><span><strong>FlatFile</strong><small>CMS</small></span>
            </a>
            <button class="sidebar-close" type="button" data-admin-menu-close aria-label="Zamknij menu">×</button>
        </div>
        <nav class="admin-navigation" aria-label="Nawigacja panelu">
            <?php foreach ($links as [$url, $name, $label, $description]): $current = $active === $name; ?>
                <a href="<?= $url ?>" class="<?= $current ? 'active' : '' ?>"<?= $current ? ' aria-current="page"' : '' ?>>
                    <span class="nav-indicator"></span><span><strong><?= $label ?></strong><small><?= $description ?></small></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-account">
            <span><?= $escape($email) ?></span>
            <form method="post" action="/admin/logout">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                <button type="submit" class="sidebar-logout">Wyloguj</button>
            </form>
        </div>
    </aside>
    <div class="admin-workspace">
        <header class="admin-topbar">
            <button class="menu-toggle" type="button" data-admin-menu aria-label="Otwórz menu" aria-expanded="false"><span></span><span></span><span></span></button>
            <a class="mobile-brand" href="/admin">FlatFile CMS</a>
            <form method="post" action="/admin/logout">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                <button type="submit" class="topbar-logout">Wyloguj</button>
            </form>
        </header>
        <main class="admin-main" id="admin-content">
            <header class="page-heading"><div><p class="eyebrow">Panel administracyjny</p><h1><?= $escape($title) ?></h1></div></header>
            <div class="admin-content"><?= $content ?></div>
        </main>
    </div>
</div>
