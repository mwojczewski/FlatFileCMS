<?php

declare(strict_types=1);

$locale = $context->locale();
?>
<section class="code-showcase" id="developer-experience">
    <div class="container code-showcase__grid">
        <div class="code-showcase__panel">
            <div class="code-showcase__bar"><span><?= $context->escape($data['filename']) ?></span><button type="button"
                    data-copy-code data-copy-label="<?= $locale === 'pl' ? 'Kopiuj' : 'Copy' ?>"
                    data-copied-label="<?= $locale === 'pl' ? 'Skopiowano' : 'Copied' ?>"><?= $locale === 'pl' ? 'Kopiuj' : 'Copy' ?></button>
            </div>
            <pre><code><?= $context->escape($data['code']) ?></code></pre>
        </div>
        <div class="code-showcase__copy">
            <p class="section-kicker section-kicker--dark"><span></span><?= $context->escape($data['eyebrow']) ?></p>
            <h2><?= $context->escape($data['title']) ?></h2>
            <p><?= $context->escape($data['description']) ?></p>
            <ul><?php foreach ($data['bullets'] as $bullet): ?>
                    <li><span>✓</span><?= $context->escape($bullet['text']) ?></li><?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>