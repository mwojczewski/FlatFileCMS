<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/app', __DIR__ . '/bin', __DIR__ . '/bootstrap', __DIR__ . '/config', __DIR__ . '/public', __DIR__ . '/tests'])
    ->name(['*.php', 'cms']);

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0' => true,
        'declare_strict_types' => true,
        'ordered_imports' => true,
    ])
    ->setFinder($finder);
