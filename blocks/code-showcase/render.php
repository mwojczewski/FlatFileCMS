<?php

declare(strict_types=1);

$locale = $context->locale();
?>
<section class="code-showcase" id="developer-experience">
    <div class="container code-showcase__grid">
        <div class="code-showcase__panel">
            <div class="code-showcase__bar">
                <span><?= $context->escape($data['filename']) ?></span>
                <button type="button" data-copy-code data-copy-label="<?= $locale === 'pl' ? 'Kopiuj' : 'Copy' ?>"
                    data-copied-label="<?= $locale === 'pl' ? 'Skopiowano' : 'Copied' ?>">
                    <?= $locale === 'pl' ? 'Kopiuj' : 'Copy' ?>
                </button>
            </div>
            <pre><code><?= $context->escape($data['code']) ?></code></pre>
        </div>
        <div class="code-showcase__copy">
            <p class="section-kicker section-kicker--dark">
                <span></span>
                <?= $context->escape($data['eyebrow']) ?>
            </p>
            <h2><?= $context->escape($data['title']) ?></h2>
            <p><?= $context->escape($data['description']) ?></p>
            <ul>
                <?php foreach ($data['bullets'] as $bullet): ?>
                    <li>
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                class="bi bi-check" viewBox="0 0 16 16">
                                <path
                                    d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425z" />
                            </svg>
                        </span>
                        <?= $context->escape($bullet['text']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>