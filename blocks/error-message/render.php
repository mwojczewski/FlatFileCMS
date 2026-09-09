<?php

declare(strict_types=1);

$title = $data['title'];
$content = $data['content'] ?? '';
$buttonLabel = $data['buttonLabel'] ?? '';
$buttonUrl = $data['buttonUrl'] ?? '';
if ($buttonUrl === '/' && $error !== null) {
    $buttonUrl = $error->homepageUrl();
}
?>
<section class="error-message">
    <div class="container error-message__inner">
        <?php if ($error !== null): ?>
            <p class="error-message__status">
                <strong><?= $context->escape($error->status()) ?></strong>
                <span><?= $context->escape($error->description()) ?></span>
            </p>
        <?php endif; ?>
        <h1><?= $context->escape($title) ?></h1>
        <?php if ($content !== ''): ?>
            <div class="error-message__content"><?= $context->markdown($content) ?></div>
        <?php endif; ?>
        <?php if ($buttonLabel !== '' && $buttonUrl !== ''): ?>
            <a class="error-message__button"
                href="<?= $context->url($buttonUrl) ?>"><?= $context->escape($buttonLabel) ?></a>
        <?php endif; ?>
    </div>
</section>