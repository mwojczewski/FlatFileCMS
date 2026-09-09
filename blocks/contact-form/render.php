<?php

declare(strict_types=1);

$status = isset($_GET['form']) && \is_string($_GET['form']) ? $_GET['form'] : '';
$inputType = static fn(string $type): string => \in_array($type, ['email', 'tel'], true) ? $type : 'text';
$options = static fn(mixed $value): array => \is_string($value)
    ? array_values(array_filter(array_map(trim(...), preg_split('/\R/u', $value) ?: [])))
    : [];
?>
<section class="contact-form" id="contact-form">
    <div class="container contact-form__inner">
        <header class="contact-form__header">
            <?php if (($data['eyebrow'] ?? '') !== ''): ?>
                <p class="section-kicker">
                    <span></span>
                    <?= $context->escape($data['eyebrow']) ?>
                </p>
            <?php endif; ?>
            <h1><?= $context->escape($data['title']) ?></h1>
            <?php if (($data['introduction'] ?? '') !== ''): ?>
                <p><?= $context->escape($data['introduction']) ?></p>
            <?php endif; ?>
        </header>

        <?php if ($status === 'sent'): ?>
            <div class="contact-form__notice contact-form__notice--success" role="status">
                <?= $context->escape($data['success_message']) ?>
            </div>
        <?php elseif ($status === 'rate-limited'): ?>
            <div class="contact-form__notice contact-form__notice--error" role="alert">
                <?= $context->escape($data['rate_limit_message']) ?>
            </div>
        <?php elseif ($status === 'invalid'): ?>
            <div class="contact-form__notice contact-form__notice--error" role="alert">
                <?= $context->escape($data['error_message']) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/forms/contact" class="contact-form__form">
            <input type="hidden" name="page_id" value="<?= $context->escape($data['_page_id']) ?>">
            <input type="hidden" name="block_id" value="<?= $context->escape($data['_block_id']) ?>">
            <input type="hidden" name="locale" value="<?= $context->escape($context->locale()) ?>">
            <input type="hidden" name="return_path" value="<?= $context->escape($data['_return_path']) ?>">
            <div class="contact-form__trap" aria-hidden="true">
                <label>Website
                    <input name="website" tabindex="-1" autocomplete="off">
                </label>
            </div>
            <?php foreach ($data['fields'] as $field):
                $name = (string) $field['name'];
                $type = (string) $field['type'];
                $required = ($field['required'] ?? false) === true;
                $id = 'contact-' . $data['_block_id'] . '-' . $name;
                ?>
                <label class="contact-form__field<?= $type === 'checkbox' ? ' contact-form__field--checkbox' : '' ?>"
                    for="<?= $context->escape($id) ?>">
                    <?php if ($type === 'checkbox'): ?>
                        <input id="<?= $context->escape($id) ?>" type="checkbox" name="fields[<?= $context->escape($name) ?>]"
                            value="1" <?= $required ? ' required' : '' ?>>
                        <span><?= $context->escape($field['label']) ?><?= $required ? ' *' : '' ?></span>
                    <?php else: ?>
                        <span><?= $context->escape($field['label']) ?><?= $required ? ' *' : '' ?></span>
                        <?php if ($type === 'textarea'): ?>
                            <textarea id="<?= $context->escape($id) ?>" name="fields[<?= $context->escape($name) ?>]" rows="6"
                                <?= $required ? ' required' : '' ?>></textarea>
                        <?php elseif ($type === 'select'): ?>
                            <select id="<?= $context->escape($id) ?>" name="fields[<?= $context->escape($name) ?>]" <?= $required ? ' required' : '' ?>>
                                <option value="">—</option>
                                <?php foreach ($options($field['options'] ?? '') as $option): ?>
                                    <option value="<?= $context->escape($option) ?>"><?= $context->escape($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input id="<?= $context->escape($id) ?>" type="<?= $inputType($type) ?>"
                                name="fields[<?= $context->escape($name) ?>]" <?= $required ? ' required' : '' ?>>
                        <?php endif; ?>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
            <button type="submit" class="button"><?= $context->escape($data['submit_label']) ?></button>
        </form>
    </div>
</section>