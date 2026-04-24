<?php

declare(strict_types=1);

/**
 * Prints a segment count of L3 public symbols still missing @covers, grouped by
 * top `Waaseyaa\<Segment>\...` namespace.
 *
 * Usage (after regenerating the Layer 3 audit):
 *   php tools/audit/segment_l3_covers_by_package.php
 */
$root = realpath(__DIR__ . '/../..');
$path = $root . '/build/layer3-audit/symbol_test_map_layer3.json';
if (!is_file($path)) {
    fwrite(STDERR, "Missing {$path} — run: php tools/audit/GenerateLayerAudit.php 3\n");
    exit(1);
}

/** @var array<string, mixed> $j */
$j = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$missing = (array) ($j['public_non_internal_symbols_lacking_at_covers'] ?? []);
$bySegment = [];
foreach ($missing as $row) {
    if (!\is_array($row)) {
        continue;
    }
    $fqcn = (string) ($row['fqcn'] ?? '');
    if (!str_starts_with($fqcn, 'Waaseyaa\\')) {
        $seg = 'non_waaseyaa';
    } else {
        $rest = substr($fqcn, \strlen('Waaseyaa\\'));
        $seg = explode('\\', $rest, 2)[0] ?: 'unknown';
    }
    $bySegment[$seg] = ($bySegment[$seg] ?? 0) + 1;
}
ksort($bySegment, SORT_STRING);

$out = "L3 @covers gaps by Waaseyaa\\\\<segment> (from symbol_test_map_layer3.json)\n";
$out .= 'Total missing: ' . \count($missing) . "\n\n";
foreach ($bySegment as $seg => $n) {
    $out .= str_pad($seg, 22, ' ', \STR_PAD_RIGHT) . (string) $n . "\n";
}
$out .= "\nTo shrink: add @internal, or add PHPDoc @covers in L3 package tests (see L3 remediation pilots).\n";
echo $out;
