<?php

declare(strict_types=1);

$locale = $context->locale();
$anchor = $data['anchor'] ?? '';
?>
<section class="diagnostic-table"<?= $anchor !== '' ? ' id="' . $context->escape($anchor) . '"' : '' ?>>
    <div class="container">
        <header class="diagnostic-table__heading">
            <p><?= $context->escape($data['eyebrow']) ?></p>
            <div>
                <h2><?= $context->escape($data['title']) ?></h2>
                <p><?= $context->escape($data['intro']) ?></p>
            </div>
        </header>
        <div class="diagnostic-table__scroller">
            <div class="diagnostic-table__grid" role="table" aria-label="<?= $context->escape($data['title']) ?>">
                <div class="diagnostic-table__row diagnostic-table__row--head" role="row">
                    <span role="columnheader"><?= $locale === 'pl' ? 'Warstwa' : 'Layer' ?></span>
                    <span role="columnheader"><?= $locale === 'pl' ? 'Problem / objaw' : 'Problem / symptom' ?></span>
                    <span role="columnheader"><?= $locale === 'pl' ? 'Prawdopodobna przyczyna' : 'Likely cause' ?></span>
                    <span role="columnheader"><?= $locale === 'pl' ? 'Co sprawdzić' : 'What to check' ?></span>
                </div>
                <?php foreach ($data['rows'] as $row): ?>
                    <div class="diagnostic-table__row" role="row">
                        <strong role="cell" data-label="<?= $locale === 'pl' ? 'Warstwa' : 'Layer' ?>"><?= $context->escape($row['layer']) ?></strong>
                        <span role="cell" data-label="<?= $locale === 'pl' ? 'Problem / objaw' : 'Problem / symptom' ?>"><?= $context->escape($row['problem']) ?></span>
                        <span role="cell" data-label="<?= $locale === 'pl' ? 'Przyczyna' : 'Cause' ?>"><?= $context->escape($row['cause']) ?></span>
                        <div role="cell" data-label="<?= $locale === 'pl' ? 'Co sprawdzić' : 'What to check' ?>"><?= $context->markdown($row['check']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if (($data['note'] ?? '') !== ''): ?>
            <aside class="diagnostic-table__note">
                <strong><?= $locale === 'pl' ? 'Reguła diagnostyczna' : 'Diagnostic rule' ?></strong>
                <p><?= $context->escape($data['note']) ?></p>
            </aside>
        <?php endif; ?>
    </div>
</section>
