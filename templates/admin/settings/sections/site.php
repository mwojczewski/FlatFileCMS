<section class="form-section settings-component" id="site-settings">
    <div class="section-heading"><div><p class="eyebrow">Witryna</p><h2>Tożsamość i rendering</h2></div><p>Adres witryny jest źródłem dla canonical i sitemap.xml.</p></div>
    <div class="settings-grid">
        <label>Nazwa strony<input name="site_name" value="<?= $escape($string($site['name'] ?? '')) ?>" required></label>
        <label>Publiczny URL<input name="site_url" value="<?= $escape($string($site['url'] ?? '')) ?>" required placeholder="https://example.com"></label>
        <label>Domyślny layout<select name="default_layout"><?php foreach ($layouts as $layout): ?><option value="<?= $escape($layout) ?>"<?= ($site['defaultLayout'] ?? null) === $layout ? ' selected' : '' ?>><?= $escape($layout) ?></option><?php endforeach; ?></select></label>
    </div>
    <?php $icons = $mapping($site['icons'] ?? []); ?>
    <fieldset class="form-card site-icons-card">
        <legend>Ikony witryny</legend>
        <p class="hint">Podaj ścieżkę od katalogu publicznego, np. <code>/assets/icons/favicon.svg</code>, albo pełny adres HTTPS. Puste pola nie wygenerują znacznika.</p>
        <div class="settings-grid">
            <label>Favicon SVG<input name="site_icon_svg" value="<?= $escape($string($icons['svg'] ?? '')) ?>" placeholder="/assets/icons/favicon.svg" inputmode="url"></label>
            <label>Favicon ICO<input name="site_icon_ico" value="<?= $escape($string($icons['ico'] ?? '')) ?>" placeholder="/favicon.ico" inputmode="url"></label>
            <label>Favicon PNG 32×32<input name="site_icon_png_32" value="<?= $escape($string($icons['png32'] ?? '')) ?>" placeholder="/assets/icons/favicon-32x32.png" inputmode="url"></label>
            <label>Favicon PNG 16×16<input name="site_icon_png_16" value="<?= $escape($string($icons['png16'] ?? '')) ?>" placeholder="/assets/icons/favicon-16x16.png" inputmode="url"></label>
            <label>Apple Touch Icon 180×180<input name="site_apple_touch_icon" value="<?= $escape($string($icons['appleTouch'] ?? '')) ?>" placeholder="/apple-touch-icon.png" inputmode="url"></label>
            <label>Apple Touch Icon precomposed<input name="site_apple_touch_icon_precomposed" value="<?= $escape($string($icons['appleTouchPrecomposed'] ?? '')) ?>" placeholder="/apple-touch-icon-precomposed.png" inputmode="url"></label>
            <label>Web App Manifest<input name="site_web_manifest" value="<?= $escape($string($icons['manifest'] ?? '')) ?>" placeholder="/site.webmanifest" inputmode="url"></label>
        </div>
    </fieldset>
</section>
