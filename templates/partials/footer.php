<?php

declare(strict_types=1);

$menus = $data['menus'];
$footer = $menus['footer'] ?? [];
$locale = $context->locale();

$localizedUrl = static function (string $url) use ($locale): string {
    if ($url === '/' || str_starts_with($url, '/#')) {
        return "/{$locale}/" . ltrim($url, '/');
    }

    return $url;
};
?>
<footer class="site-footer">
    <div class="container">
        <div class="site-footer__top">
            <div>
                <div class="site-footer__brand">FlatFile CMS</div>
                <p class="site-footer__tagline">
                    <?= $locale === 'pl' ? 'Nowoczesna warstwa treści dla zespołów, które chcą zachować prostotę plików i pełną kontrolę nad kodem.' : 'A modern content layer for teams that want to keep the simplicity of files and full control over their code.' ?>
                </p>
            </div>
            <div class="site-footer__links">
                <?php foreach ($footer as $item) {
                    $target = $item['target'] === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>
                    <a href="<?= $context->escape($localizedUrl($item['url'])) ?>" <?= $target ?>><?= $context->escape($item['label']) ?></a>
                <?php } ?>
            </div>
        </div>
        <div class="site-footer__bottom"><span>&copy; <?= date('Y') ?> FlatFile CMS</span><span>BUILT WITH PHP · YAML ·
                CARE</span></div>
    </div>
</footer>