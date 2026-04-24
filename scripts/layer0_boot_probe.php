#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CI-safe bootstrap timing: Composer autoload only (no .env / kernel).
 * Usage: php scripts/layer0_boot_probe.php
 */

$root = dirname(__DIR__);

$t0 = hrtime(true);
require $root . '/vendor/autoload.php';
$autoloadMs = (hrtime(true) - $t0) / 1e6;

echo json_encode([
    'autoload_ms' => $autoloadMs,
    'php_version' => PHP_VERSION,
    'opcache_cli' => ini_get('opcache.enable_cli'),
], JSON_PRETTY_PRINT), PHP_EOL;
