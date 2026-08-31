<?php

declare(strict_types=1);

$title = $data['title'];
$content = $data['content'] ?? '';
$image = $data['image'] ?? null;
$alignment = $data['alignment'];
?>
<section class="hero hero--<?= $context->escape($alignment) ?>">
    <div class="container">
        <h1><?= $context->escape($title) ?></h1>
        <?php if ($content !== ''): ?>
            <div class="hero__content"><?= $context->markdown($content) ?></div>
        <?php endif; ?>
        <?php if ($image !== null): ?>
            <?= $context->image($image) ?>
        <?php endif; ?>
    </div>
</section>