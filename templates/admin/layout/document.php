<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="<?= $escape($csrfToken) ?>">
    <title><?= $escape($title) ?> — FlatFile CMS</title>
    <?= $styles ?>
    <?= $scripts ?>
</head>
<body class="<?= $escape($bodyClass) ?>">
<?= $body ?>
</body>
</html>
