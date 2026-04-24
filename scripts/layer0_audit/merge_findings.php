#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Merge multiple JSON files containing { "findings": [...] } into one array.
 * Usage: php scripts/layer0_audit/merge_findings.php out.json in1.json in2.json ...
 */

$args = array_slice($argv, 1);
if (count($args) < 2) {
    fwrite(STDERR, "Usage: {$argv[0]} out.json in1.json [in2.json ...]\n");
    exit(1);
}
$out = array_shift($args);
$merged = [];
foreach ($args as $f) {
    if (!is_readable($f)) {
        continue;
    }
    $data = json_decode((string) file_get_contents($f), true);
    if (!is_array($data)) {
        continue;
    }
    $chunk = $data['findings'] ?? $data;
    if (is_array($chunk) && array_is_list($chunk)) {
        foreach ($chunk as $item) {
            if (is_array($item)) {
                $merged[] = $item;
            }
        }
    }
}
file_put_contents($out, json_encode(['findings' => $merged], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
fwrite(STDERR, 'Merged ' . count($merged) . " findings to {$out}\n");
