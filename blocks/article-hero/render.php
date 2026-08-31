<?php

declare(strict_types=1);

$locale = $context->locale();
?>
<header class="article-hero">
    <div class="container article-hero__copy"><a href="/<?= $context->escape($locale) ?>/insights">← Insights</a>
        <p><span><?= $context->escape($data['category']) ?></span><time
                datetime="<?= $context->escape($data['date']) ?>"><?= $context->escape($data['date']) ?></time></p>
        <h1><?= $context->escape($data['title']) ?></h1>
        <div class="article-hero__lead"><?= $context->escape($data['lead']) ?></div>
    </div>
    <div class="container article-hero__image"><?= $context->image($data['image']) ?></div>
</header>