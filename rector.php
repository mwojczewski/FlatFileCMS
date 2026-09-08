<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/blocks',
        __DIR__ . '/bootstrap',
        __DIR__ . '/config',
        __DIR__ . '/integration',
        __DIR__ . '/public',
        __DIR__ . '/templates',
        __DIR__ . '/tests',
    ])
    // uncomment to reach your current PHP version
    // ->withPhpSets()
    ->withSets([SetList::DEAD_CODE])
    ->withTypeCoverageLevel(0)
    ->withCodeQualityLevel(0);
