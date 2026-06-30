<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/includes',
        __DIR__ . '/hizli-kasa.php',
    ])
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/includes/plugin-update-checker',
        \Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector::class,
    ])
    ->withPhpSets(php74: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: false,
    );
