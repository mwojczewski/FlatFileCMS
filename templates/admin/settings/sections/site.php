<section class="form-section settings-component" id="site-settings">
    <div class="section-heading"><div><p class="eyebrow">Witryna</p><h2>Tożsamość i rendering</h2></div><p>Adres witryny jest źródłem dla canonical i sitemap.xml.</p></div>
    <div class="settings-grid">
        <label>Nazwa strony<input name="site_name" value="<?= $escape($string($site['name'] ?? '')) ?>" required></label>
        <label>Publiczny URL<input name="site_url" value="<?= $escape($string($site['url'] ?? '')) ?>" required placeholder="https://example.com"></label>
        <label>Domyślny layout<select name="default_layout"><?php foreach ($layouts as $layout): ?><option value="<?= $escape($layout) ?>"<?= ($site['defaultLayout'] ?? null) === $layout ? ' selected' : '' ?>><?= $escape($layout) ?></option><?php endforeach; ?></select></label>
    </div>
</section>
