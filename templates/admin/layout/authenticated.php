<?php
$links = [
    ['/admin', 'dashboard', 'Pulpit', 'Przegląd systemu', 'home'],
    ['/admin/pages', 'pages', 'Strony', 'Treść i edytor bloków', 'file'],
    ['/admin/navigation', 'navigation', 'Nawigacja', 'Menu i hierarchia linków', 'tree'],
    ['/admin/redirects', 'redirects', 'Przekierowania', 'Reguły adresów 3xx', 'redirect'],
    ['/admin/settings', 'settings', 'Konfiguracja', 'SEO, witryna i multimedia', 'settings'],
    ['/admin/logs', 'logs', 'Logi', 'Błędy i zdarzenia aplikacji', 'logs'],
    ['/admin/users', 'users', 'Administratorzy', 'Konta panelu', 'users'],
    ['/admin/security', 'account', 'Konto', 'Hasło i zabezpieczenia', 'account'],
];
$icon = static function (string $name): string {
    $paths = match ($name) {
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5M9 21v-7h6v7"/>',
        'file' => '<path d="M6 2h8l5 5v15H6z"/><path d="M14 2v6h5M9 13h6M9 17h6"/>',
        'tree' => '<rect x="4" y="3" width="6" height="5" rx="1"/><rect x="14" y="16" width="6" height="5" rx="1"/><rect x="4" y="16" width="6" height="5" rx="1"/><path d="M7 8v4h10v4M7 12v4"/>',
        'redirect' => '<path d="M17 3l4 4-4 4"/><path d="M3 7h18M7 21l-4-4 4-4"/><path d="M21 17H3"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
        'logs' => '<path d="M5 3h14v18H5z"/><path d="M9 8h6M9 12h6M9 16h4"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'account' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        default => '<circle cx="12" cy="12" r="8"/>',
    };

    return '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
};
?>
<a class="skip-link" href="#admin-content">Przejdź do treści</a>
<div class="admin-shell">
    <div class="admin-backdrop" data-admin-backdrop hidden></div>
    <aside class="admin-sidebar" id="admin-navigation" data-admin-sidebar>
        <div class="admin-brand">
            <a href="/admin" aria-label="FlatFile CMS — panel">
                <span class="admin-brand-mark">F</span>
                <span class="admin-brand-copy">
                    <strong>FlatFile</strong>
                    <small>CMS</small>
                </span>
            </a>
            <button class="sidebar-collapse" type="button" data-sidebar-collapse aria-label="Zwiń panel boczny"
                title="Zwiń panel boczny" aria-expanded="true">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="m14 7-5 5 5 5" />
                </svg>
            </button>
            <button class="sidebar-close" type="button" data-admin-menu-close aria-label="Zamknij menu">×</button>
        </div>
        <nav class="admin-navigation" aria-label="Nawigacja panelu">
            <p class="navigation-label">Zarządzanie</p>
            <?php foreach ($links as $index => [$url, $name, $label, $description, $iconName]):
                if ($index === 5): ?>
                    <p class="navigation-label navigation-label-secondary">System</p><?php endif;
                $current = $active === $name; ?>
                <a href="<?= $url ?>" class="<?= $current ? 'active' : '' ?>" <?= $current ? ' aria-current="page"' : '' ?>
                    title="<?= $escape($label . ' — ' . $description) ?>">
                    <span class="nav-icon"><?= $icon($iconName) ?></span>
                    <span class="nav-copy">
                        <strong><?= $label ?></strong>
                        <small><?= $description ?></small>
                    </span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-status">
            <span aria-hidden="true"></span>
            <span>Panel gotowy</span>
        </div>
        <div class="sidebar-account">
            <span class="account-avatar" aria-hidden="true" title="<?= $escape($displayName) ?>">
                <?= $escape($accountInitials) ?>
            </span>
            <span class="account-copy">
                <strong><?= $escape($displayName) ?></strong>
                <small><?= $escape($email) ?></small>
            </span>
            <form method="post" action="/admin/logout">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                <button type="submit" class="sidebar-logout" aria-label="Wyloguj" title="Wyloguj">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-box-arrow-right" viewBox="0 0 16 16">
                        <path fill-rule="evenodd"
                            d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0z" />
                        <path fill-rule="evenodd"
                            d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708z" />
                    </svg>
                </button>
            </form>
        </div>
    </aside>
    <div class="admin-workspace">
        <header class="admin-topbar">
            <div class="topbar-start">
                <button class="menu-toggle" type="button" data-admin-menu aria-label="Otwórz menu"
                    aria-controls="admin-navigation"
                    aria-expanded="false"><span></span><span></span><span></span></button>
                <a class="mobile-brand" href="/admin"><span class="admin-brand-mark">F</span><strong>FlatFile
                        CMS</strong></a>
                <span class="topbar-context"><?= $escape($title) ?></span>
            </div>
            <div class="topbar-actions">
                <a class="topbar-site-link" href="/" target="_blank" rel="noopener">Otwórz witrynę <span
                        aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                            class="bi bi-arrow-up-right-circle" viewBox="0 0 16 16">
                            <path fill-rule="evenodd"
                                d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8m15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.854 10.803a.5.5 0 1 1-.708-.707L9.243 6H6.475a.5.5 0 1 1 0-1h3.975a.5.5 0 0 1 .5.5v3.975a.5.5 0 1 1-1 0V6.707z" />
                        </svg>
                    </span>
                </a>
                <span class="topbar-avatar" aria-hidden="true"><?= $escape($accountInitials) ?></span>
                <form method="post" action="/admin/logout">
                    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                    <button type="submit" class="topbar-logout">Wyloguj</button>
                </form>
            </div>
        </header>
        <main class="admin-main" id="admin-content">
            <header class="page-heading">
                <div>
                    <p class="eyebrow">Panel administracyjny</p>
                    <h1><?= $escape($title) ?></h1>
                </div>
            </header>
            <div class="admin-content"><?= $content ?></div>
        </main>
    </div>
</div>