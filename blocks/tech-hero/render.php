<?php

declare(strict_types=1);

$metrics = $data['metrics'];
?>
<section class="tech-hero">
    <div class="tech-hero__glow" aria-hidden="true"></div>
    <div class="container tech-hero__grid">
        <div class="tech-hero__copy">
            <p class="section-kicker"><span></span><?= $context->escape($data['eyebrow']) ?></p>
            <h1><?= $context->escape($data['title']) ?></h1>
            <p class="tech-hero__lead"><?= $context->escape($data['lead']) ?></p>
            <div class="tech-hero__actions">
                <a class="button button--primary"
                    href="<?= $context->url($data['primary_url']) ?>"><?= $context->escape($data['primary_label']) ?>
                    <span aria-hidden="true">→</span></a>
                <a class="button button--ghost"
                    href="<?= $context->url($data['secondary_url']) ?>"><?= $context->escape($data['secondary_label']) ?></a>
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
            <div class="terminal__bar"><span></span><span></span><span></span><code>content.yml</code></div>
            <div class="terminal__body">
                <p><i>1</i><span class="c-key">schemaVersion:</span> <span class="c-value">1</span></p>
                <p><i>2</i><span class="c-key">layout:</span> <span class="c-string">default</span></p>
                <p><i>3</i><span class="c-key">blocks:</span></p>
                <p><i>4</i>&nbsp;&nbsp;- <span class="c-key">type:</span> <span class="c-string">tech-hero</span></p>
                <p><i>5</i>&nbsp;&nbsp;&nbsp;&nbsp;<span class="c-key">enabled:</span> <span class="c-value">true</span>
                </p>
                <p><i>6</i>&nbsp;&nbsp;&nbsp;&nbsp;<span class="c-key">data:</span></p>
                <p><i>7</i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="c-key">runtime:</span> <span
                        class="c-string">PHP <?= $context->escape($data['version']) ?></span></p>
            </div>
            <div class="terminal__status"><span><b></b> VALIDATED</span><span>SSR · API · YAML</span></div>
        </div>
    </div>
</section>