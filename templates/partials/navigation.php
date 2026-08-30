<?php

declare(strict_types=1);

$menus = $data['menus'];
$main = $menus['main'] ?? [];

$renderItems = static function (array $items) use (&$renderItems, $context): string {
    if ($items === []) {
        return '';
    }

    $html = '<ul>';
    foreach ($items as $item) {
        $target = $item['target'] === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : '';
        $html .= '<li><a href="' . $context->escape($item['url']) . '"' . $target . '>';
        $html .= $context->escape($item['label']) . '</a>';
        $html .= $renderItems($item['children']);
        $html .= '</li>';
    }

    return $html . '</ul>';
};
?>
<?php if ($main !== []): ?>
    <nav aria-label="Main navigation"><?= $renderItems($main) ?></nav>
<?php endif; ?>
