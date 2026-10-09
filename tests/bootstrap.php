<?php

declare(strict_types=1);

// Standalone checkout first, otherwise the Contao host project (bundle symlinked in __bundles/)
foreach ([__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../../vendor/autoload.php'] as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;

        return;
    }
}

throw new RuntimeException('Composer autoloader not found, run "composer install".');
