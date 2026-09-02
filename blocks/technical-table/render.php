<?php

declare(strict_types=1);
?>
<section class="technical-table">
    <div class="container">
        <header class="technical-table__heading">
            <p><?= $context->escape($data['eyebrow']) ?></p>
            <div>
                <h2><?= $context->escape($data['title']) ?></h2>
                <p><?= $context->escape($data['intro']) ?></p>
            </div>
        </header>
        <div class="technical-table__grid" role="table" aria-label="<?= $context->escape($data['title']) ?>">
            <div class="technical-table__row technical-table__row--head" role="row">
                <?php for ($column = 1; $column <= 4; ++$column): ?>
                    <span role="columnheader"><?= $context->escape($data['column_' . $column]) ?></span>
                <?php endfor; ?>
            </div>
            <?php foreach ($data['rows'] as $row): ?>
                <div class="technical-table__row" role="row">
                    <?php for ($column = 1; $column <= 4; ++$column): ?>
                        <<?= $column === 1 ? 'strong' : 'span' ?> role="cell" data-label="<?= $context->escape($data['column_' . $column]) ?>"><?= $context->escape($row['cell_' . $column]) ?></<?= $column === 1 ? 'strong' : 'span' ?>>
                    <?php endfor; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
