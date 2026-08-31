<?php

declare(strict_types=1);
?>
<section class="tech-stack" aria-label="<?= $context->escape($data['label']) ?>">
    <div class="container">
        <p><?= $context->escape($data['label']) ?></p>
        <ul><?php foreach ($data['items'] as $item): ?>
                <li><strong><?= $context->escape($item['name']) ?></strong><span><?= $context->escape($item['detail']) ?></span>
                </li><?php endforeach; ?>
        </ul>
    </div>
</section>