<?php

declare(strict_types=1);

$locale = $context->locale();
?>
<section class="article-content">
    <div class="container article-content__grid">
        <aside>FlatFile CMS<br><span>ENGINEERING NOTES</span></aside>
        <article><?= $context->markdown($data['content']) ?><?php if (($data['note'] ?? '') !== ''): ?>
                <div class="article-content__note">
                    <strong><?= $locale === 'pl' ? 'Warto zapamiętać' : 'Key takeaway' ?></strong>
                    <p><?= $context->escape($data['note']) ?></p>
                </div><?php endif; ?>
        </article>
    </div>
</section>