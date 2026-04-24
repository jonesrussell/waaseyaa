<?php

declare(strict_types=1);

/**
 * Layer 2 (Content Types) forensic audit. Same as:
 *   php tools/audit/GenerateLayerAudit.php 2
 */
$argv[1] = '2';
require __DIR__ . '/GenerateLayerAudit.php';
