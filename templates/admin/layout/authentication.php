<main class="auth-shell" id="admin-content">
    <div class="auth-layout">
        <aside class="auth-aside">
            <a class="auth-brand" href="/admin/login"><span
                    class="admin-brand-mark">F</span><span><strong>FlatFile</strong><small>CMS</small></span></a>
            <div class="auth-aside-copy">
                <p class="eyebrow">Panel administracyjny</p>
                <h2>Treść w plikach.<br>Kontrola w Twoich rękach.</h2>
                <p>Lekki panel do zarządzania stronami, blokami i strukturą witryny.</p>
            </div>
            <ul>
                <li><span aria-hidden="true">✓</span>Bez bazy danych dla treści</li>
                <li><span aria-hidden="true">✓</span>Bezpieczne logowanie WebAuthn</li>
                <li><span aria-hidden="true">✓</span>Pełna kontrola nad publikacją</li>
            </ul>
        </aside>
        <section class="auth-card">
            <a class="auth-brand auth-card-brand" href="/admin/login"><span
                    class="admin-brand-mark">F</span><span><strong>FlatFile</strong><small>CMS</small></span></a>
            <div class="auth-heading">
                <p class="eyebrow">Bezpieczny panel</p>
                <h1><?= $escape($title) ?></h1>
            </div>
            <?= $content ?>
            <p class="auth-caption">Połączenie z panelem jest chronione.</p>
        </section>
    </div>
</main>