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
                aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                    class="bi bi-arrow-up-right" viewBox="0 0 16 16">
                    <path fill-rule="evenodd"
                        d="M14 2.5a.5.5 0 0 0-.5-.5h-6a.5.5 0 0 0 0 1h4.793L2.146 13.146a.5.5 0 0 0 .708.708L13 3.707V8.5a.5.5 0 0 0 1 0z" />
                </svg>
            </span></a>
    </div>
</section>