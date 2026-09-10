<?php

declare(strict_types=1);

$metrics = $data['metrics'];
?>
<section class="tech-hero">
    <div class="tech-hero__glow" aria-hidden="true"></div>
    <div class="container tech-hero__grid">
        <div class="tech-hero__copy">
            <p class="section-kicker">
                <span></span>
                <?= $context->escape($data['eyebrow']) ?>
            </p>
            <h1><?= $context->escape($data['title']) ?></h1>
            <p class="tech-hero__lead"><?= $context->escape($data['lead']) ?></p>
            <div class="tech-hero__actions">
                <a class="button button--primary" href="<?= $context->url($data['primary_url']) ?>">
                    <?= $context->escape($data['primary_label']) ?>
                    <span aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                            class="bi bi-arrow-right" viewBox="0 0 16 16">
                            <path fill-rule="evenodd"
                                d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8" />
                        </svg>
                    </span>
                </a>
                <a class="button button--ghost" href="<?= $context->url($data['secondary_url']) ?>">
                    <?= $context->escape($data['secondary_label']) ?>
                </a>
            </div>
            <dl class="tech-hero__metrics">
                <?php foreach ($metrics as $metric): ?>
                    <div>
                        <dt><?= $context->escape($metric['value']) ?></dt>
                        <dd><?= $context->escape($metric['label']) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
        <div class="tech-hero__terminal" aria-label="Podgląd działania FlatFile CMS">
            <div class="terminal__bar">
                <span class="close"></span>
                <span class="maximize"></span>
                <span class="minimize"></span>
                <code>content.yml</code>
            </div>
            <div class="terminal__body">
                <p>
                    <i>1</i>
                    <span class="c-key">schemaVersion:</span> <span class="c-value">1</span>
                </p>
                <p>
                    <i>2</i>
                    <span class="c-key">layout:</span> <span class="c-string">default</span>
                </p>
                <p>
                    <i>3</i>
                    <span class="c-key">blocks:</span>
                </p>
                <p>
                    <i>4</i>
                    &nbsp;&nbsp;- <span class="c-key">type:</span> <span class="c-string">tech-hero</span>
                </p>
                <p>
                    <i>5</i>
                    &nbsp;&nbsp;&nbsp;&nbsp;<span class="c-key">enabled:</span> <span class="c-value">true</span>
                </p>
                <p>
                    <i>6</i>
                    &nbsp;&nbsp;&nbsp;&nbsp;<span class="c-key">data:</span>
                </p>
                <p>
                    <i>7</i>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="c-key">runtime:</span> <span class="c-string">PHP
                        <?= $context->escape($data['version']) ?></span>
                </p>
            </div>
            <div class="terminal__status">
                <span>
                    <span class="heartbeat"></span>
                    <b></b>
                    &nbsp;VALIDATED
                </span>
                <span>SSR · API · YAML</span>
            </div>
        </div>
    </div>
</section>