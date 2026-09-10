<?php

declare(strict_types=1);
?>
<section class="architecture" id="architektura">
    <div class="container architecture__layout">
        <div class="architecture__copy">
            <p class="section-kicker">
                <span></span>
                <?= $context->escape($data['eyebrow']) ?>
            </p>
            <h2><?= $context->escape($data['title']) ?></h2>
            <p><?= $context->escape($data['intro']) ?></p>
            <div class="architecture__note">
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-check" viewBox="0 0 16 16">
                        <path
                            d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425z" />
                    </svg>
                </span>
                <?= $context->escape($data['note']) ?>
            </div>
        </div>
        <ol class="architecture__flow">
            <?php foreach ($data['steps'] as $index => $step): ?>
                <li>
                    <span>0<?= $context->escape((string) ($index + 1)) ?></span>
                    <div>
                        <strong><?= $context->escape($step['label']) ?></strong>
                        <small><?= $context->escape($step['detail']) ?></small>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>