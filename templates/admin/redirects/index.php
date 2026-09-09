<?php
$redirectStatuses = [
    301 => 'Stałe',
    302 => 'Tymczasowe',
    303 => 'Zmień metodę na GET',
    307 => 'Tymczasowe, zachowaj metodę',
    308 => 'Stałe, zachowaj metodę',
];
$activeRules = count(array_filter($rules, static fn($rule): bool => $rule->enabled()));
?>
<div class="structure-header redirect-header">
    <div>
        <p class="eyebrow">Routing</p>
        <h2>Przekierowania adresów</h2>
        <p class="lead">Reguły są sprawdzane przed stronami i kolekcjami.</p>
    </div>
    <div class="structure-stats"><span><strong><?= count($rules) ?></strong>
            reguł</span><span><strong><?= $activeRules ?></strong> aktywnych</span></div>
</div>

<details class="redirect-creator" <?= $rules === [] ? ' open' : '' ?>>
    <summary>
        <span class="summary-plus" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-circle"
                viewBox="0 0 16 16">
                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16" />
                <path
                    d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4" />
            </svg>
        </span>
        <span>
            <strong>Dodaj przekierowanie</strong>
            <small>Połącz stary adres z nowym miejscem docelowym</small>
        </span>
        <span class="summary-chevron" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                class="bi bi-chevron-down" viewBox="0 0 16 16">
                <path fill-rule="evenodd"
                    d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708" />
            </svg>
        </span>
    </summary>
    <form class="redirect-form redirect-create-form" method="post" action="/admin/redirects/create">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="revision"
            value="<?= $escape($revision) ?>">
        <label>Adres źródłowy<input name="source" placeholder="/stary-adres" required></label>
        <span class="redirect-arrow" aria-hidden="true">→</span>
        <label>Adres docelowy<input name="target" placeholder="/nowy-adres" required></label>
        <label>Kod HTTP<select name="status"><?php foreach ($redirectStatuses as $status => $description): ?>
                    <option value="<?= $status ?>"><?= $status ?> — <?= $escape($description) ?></option>
                <?php endforeach; ?>
            </select></label>
        <label class="check compact-check"><input type="checkbox" name="enabled" value="1"
                checked><span>Aktywne</span></label>
        <button type="submit">Dodaj regułę</button>
    </form>
</details>

<div class="redirect-list">
    <?php if ($rules === []): ?>
        <div class="empty-state"><strong>Nie ma jeszcze przekierowań.</strong>
            <p>Dodaj pierwszą regułę powyżej.</p>
        </div><?php endif; ?>
    <?php foreach ($rules as $rule): ?>
        <article class="redirect-card<?= $rule->enabled() ? '' : ' is-disabled' ?>">
            <header class="redirect-card-heading">
                <span class="redirect-status-code"><?= $rule->status() ?></span>
                <div><strong><?= $escape($rule->source()) ?> <span aria-hidden="true">→</span>
                        <?= $escape($rule->target()) ?></strong><small><?= $escape($redirectStatuses[$rule->status()] ?? 'Przekierowanie') ?></small>
                </div>
                <span class="block-visibility <?= $rule->enabled() ? 'is-visible' : '' ?>"><i
                        aria-hidden="true"></i><?= $rule->enabled() ? 'Aktywne' : 'Wyłączone' ?></span>
            </header>
            <form class="redirect-form redirect-edit-form" method="post" action="/admin/redirects/update">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="revision"
                    value="<?= $escape($revision) ?>"><input type="hidden" name="id" value="<?= $escape($rule->id()) ?>">
                <label>Źródło<input name="source" value="<?= $escape($rule->source()) ?>" required></label>
                <span class="redirect-arrow" aria-hidden="true">→</span>
                <label>Cel<input name="target" value="<?= $escape($rule->target()) ?>" required></label>
                <label>Kod<select name="status"><?php foreach ($redirectStatuses as $status => $description): ?>
                            <option value="<?= $status ?>" <?= $rule->status() === $status ? ' selected' : '' ?>><?= $status ?> —
                                <?= $escape($description) ?>
                            </option><?php endforeach; ?>
                    </select></label>
                <label class="check compact-check"><input type="checkbox" name="enabled" value="1" <?= $rule->enabled() ? ' checked' : '' ?>><span>Aktywne</span></label>
                <button type="submit">Zapisz</button>
            </form>
            <form class="redirect-delete-form" method="post" action="/admin/redirects/delete"
                data-confirm="Usunąć tę regułę przekierowania?">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="revision"
                    value="<?= $escape($revision) ?>"><input type="hidden" name="id" value="<?= $escape($rule->id()) ?>">
                <button class="button danger-text" type="submit">Usuń regułę</button>
            </form>
        </article>
    <?php endforeach; ?>
</div>