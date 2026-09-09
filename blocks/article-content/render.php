<?php

declare(strict_types=1);

$locale = $context->locale();
$anchor = $data['anchor'] ?? '';
$bookmarkTitle = $data['bookmarkTitle'] ?? 'FlatFile CMS';
$bookmarkDescription = $data['bookmarkDescription'] ?? 'ENGINEERING NOTES';
?>
<section class="article-content" <?= $anchor !== '' ? ' id="' . $context->escape($anchor) . '"' : '' ?>>
    <div class="container article-content__grid">
        <aside>
            <?= $context->escape($bookmarkTitle) ?>
            <br>
            <span><?= $context->escape($bookmarkDescription) ?></span>
        </aside>
        <article>
            <?= $context->markdown($data['content']) ?><?php if (($data['note'] ?? '') !== ''): ?>
                <div class="article-content__note">
                    <strong><?= $locale === 'pl' ? 'Warto zapamiętać' : 'Key takeaway' ?></strong>
                    <p><?= $context->escape($data['note']) ?></p>
                </div><?php endif; ?>
        </article>
    </div>
</section>