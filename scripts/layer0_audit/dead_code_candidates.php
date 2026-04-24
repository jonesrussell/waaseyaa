#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Low-confidence dead-code triage: Layer 0 classes in classmap vs ripgrep references.
 * Usage: php scripts/layer0_audit/dead_code_candidates.php autoload_classmap.json out.json
 *
 * Security greps: prefer call-shaped patterns (e.g. `exec(`) over `\\bexec\\b` to avoid
 * false positives from prose ("system default", CSS `font-family: system-ui`, etc.).
 */

$root = dirname(__DIR__, 2);

$classmapPath = $argv[1] ?? $root . '/artifacts/layer0-audit/20260424T014703Z/raw/autoload_classmap.json';
if (!str_starts_with($classmapPath, '/')) {
    $classmapPath = $root . '/' . $classmapPath;
}
$output = $argv[2] ?? $root . '/artifacts/layer0-audit/dead_code_candidates.json';

$layer0Prefixes = [
    'packages/foundation/',
    'packages/cache/',
    'packages/plugin/',
    'packages/typed-data/',
    'packages/database-legacy/',
    'packages/i18n/',
    'packages/queue/',
    'packages/scheduler/',
    'packages/state/',
    'packages/validation/',
    'packages/mail/',
    'packages/http-client/',
    'packages/ingestion/',
    'packages/error-handler/',
    'packages/geo/',
    'packages/mercure/',
    'packages/analytics/',
    'packages/oauth-provider/',
];

$json = file_get_contents($classmapPath);
if ($json === false) {
    fwrite(STDERR, "Cannot read classmap\n");
    exit(1);
}
/** @var array<string, string> $map */
$map = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

$candidates = [];
foreach ($map as $fqcn => $relPath) {
    $norm = str_replace('\\', '/', $relPath);
    if (!str_contains($norm, '/src/')) {
        continue;
    }
    $inLayer0 = false;
    foreach ($layer0Prefixes as $p) {
        if (str_contains($norm, $p)) {
            $inLayer0 = true;
            break;
        }
    }
    if (!$inLayer0) {
        continue;
    }
    $short = str_contains($fqcn, '\\') ? (substr($fqcn, strrpos($fqcn, '\\') + 1)) : $fqcn;
    $cmd = sprintf(
        'cd %s && rg -l --glob "*.php" %s packages 2>/dev/null | wc -l',
        escapeshellarg($root),
        escapeshellarg($short),
    );
    $count = (int) trim((string) shell_exec($cmd));
    $cmdFull = sprintf(
        'cd %s && rg -l --glob "*.php" %s packages 2>/dev/null | wc -l',
        escapeshellarg($root),
        escapeshellarg($fqcn),
    );
    $countFull = (int) trim((string) shell_exec($cmdFull));
    $refs = max($count, $countFull);
    if ($refs <= 1) {
        $confidence = 0.35;
        if ($refs === 1) {
            $confidence = 0.45;
        }
        $candidates[] = [
            'class' => $fqcn,
            'file' => $relPath,
            'reference_file_hits' => $refs,
            'confidence_unused' => $confidence,
            'note' => 'Heuristic only: short-name rg count across packages/. Verify DI, config, and attributes before removal.',
        ];
    }
}

usort($candidates, static fn (array $a, array $b): int => ($a['reference_file_hits'] <=> $b['reference_file_hits']));

$dir = dirname($output);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
file_put_contents($output, json_encode(['candidates' => $candidates, 'count' => count($candidates)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
fwrite(STDERR, 'Wrote ' . count($candidates) . " low-ref candidates to {$output}\n");
