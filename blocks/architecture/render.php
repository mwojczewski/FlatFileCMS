<?php

declare(strict_types=1);
?>
<section class="architecture" id="architektura">
    <div class="container architecture__layout">
        <div class="architecture__copy">
            <p class="section-kicker"><span></span><?= $context->escape($data['eyebrow']) ?></p>
            <h2><?= $context->escape($data['title']) ?></h2>
            <p><?= $context->escape($data['intro']) ?></p>
            <div class="architecture__note"><span>✓</span><?= $context->escape($data['note']) ?></div>
        </div>
        <ol class="architecture__flow">
            <?php foreach ($data['steps'] as $index => $step): ?>
                <li><span>0<?= $context->escape((string) ($index + 1)) ?></span>
                    <div>
                        <strong><?= $context->escape($step['label']) ?></strong><small><?= $context->escape($step['detail']) ?></small>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>