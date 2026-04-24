<?php

declare(strict_types=1);

/**
 * Back-compat entrypoint for Layer 1 audit. Same as:
 *   php tools/audit/GenerateLayerAudit.php 1
 */
if (!isset($argv[1]) || $argv[1] === '') {
    $argv[1] = '1';
}
require __DIR__ . '/GenerateLayerAudit.php';
