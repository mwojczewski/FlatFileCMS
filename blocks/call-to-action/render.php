<?php

declare(strict_types=1);
?>
<section class="call-to-action" id="start">
    <div class="container call-to-action__inner">
        <p class="section-kicker"><span></span><?= $context->escape($data['eyebrow']) ?></p>
        <h2><?= $context->escape($data['title']) ?></h2>
        <p><?= $context->escape($data['description']) ?></p>
        <a class="button button--light"
            href="<?= $context->url($data['button_url']) ?>"><?= $context->escape($data['button_label']) ?> <span
                aria-hidden="true">↗</span></a>
    </div>
</section>