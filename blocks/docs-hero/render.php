<?php

declare(strict_types=1);

$locale = $context->locale();
$levelLabels = [
    'podstawowy' => ['pl' => 'Podstawowy', 'en' => 'Beginner'],
    'średniozaawansowany' => ['pl' => 'Średniozaawansowany', 'en' => 'Intermediate'],
    'zaawansowany' => ['pl' => 'Zaawansowany', 'en' => 'Advanced'],
];
$level = $levelLabels[$data['level']][$locale] ?? $data['level'];
?>
<header class="docs-hero">
    <div class="container docs-hero__layout">
        <div class="docs-hero__copy">
            <p class="docs-hero__breadcrumb"><?= $context->escape($data['breadcrumb']) ?></p>
            <h1><?= $context->escape($data['title']) ?></h1>
            <p class="docs-hero__lead"><?= $context->escape($data['lead']) ?></p>
        </div>
        <aside class="docs-hero__meta"
            aria-label="<?= $locale === 'pl' ? 'Informacje o tutorialu' : 'Tutorial information' ?>">
            <dl>
                <div>
                    <dt><?= $locale === 'pl' ? 'Poziom' : 'Level' ?></dt>
                    <dd><?= $context->escape($level) ?></dd>
                </div>
                <div>
                    <dt><?= $locale === 'pl' ? 'Czas' : 'Time' ?></dt>
                    <dd><?= $context->escape($data['reading_time']) ?></dd>
                </div>
                <div>
                    <dt><?= $locale === 'pl' ? 'Aktualizacja' : 'Updated' ?></dt>
                    <dd><time
                            datetime="<?= $context->escape($data['updated']) ?>"><?= $context->escape($data['updated']) ?></time>
                    </dd>
                </div>
            </dl>
        </aside>
    </div>
    <div class="container docs-hero__topics">
        <?php foreach ($data['topics'] as $topic): ?><span><?= $context->escape($topic['label']) ?></span><?php endforeach; ?>
    </div>
</header>