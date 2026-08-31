<?php

declare(strict_types=1);

$locale = $context->locale();
?>
<footer class="site-footer">
    <div class="container">
        <div class="site-footer__top"><div><div class="site-footer__brand">FlatFile CMS</div><p class="site-footer__tagline"><?= $locale === 'pl' ? 'Nowoczesna warstwa treści dla zespołów, które chcą zachować prostotę plików i pełną kontrolę nad kodem.' : 'A modern content layer for teams that want to keep the simplicity of files and full control over their code.' ?></p></div><div class="site-footer__links"><a href="#mozliwosci"><?= $locale === 'pl' ? 'Możliwości' : 'Features' ?></a><a href="#architektura"><?= $locale === 'pl' ? 'Architektura' : 'Architecture' ?></a><a href="/api/v1/health">API status</a><a href="/admin"><?= $locale === 'pl' ? 'Panel' : 'Admin' ?></a></div></div>
        <div class="site-footer__bottom"><span>&copy; <?= date('Y') ?> FlatFile CMS</span><span>BUILT WITH PHP · YAML · CARE</span></div>
    </div>
</footer>
