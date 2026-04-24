<?php

declare(strict_types=1);

/**
 * Layer 3 (Services) forensic audit. Same as:
 *   php tools/audit/GenerateLayerAudit.php 3
 */
$argv[1] = '3';
require __DIR__ . '/GenerateLayerAudit.php';
