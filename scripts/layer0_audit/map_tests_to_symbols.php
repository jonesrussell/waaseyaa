#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Map PHPUnit JUnit XML testcase classnames to likely covered production symbols (heuristic).
 * Usage: php scripts/layer0_audit/map_tests_to_symbols.php junit1.xml [junit2.xml ...] output.json
 */

$root = dirname(__DIR__, 2);
$args = array_slice($argv, 1);
if (count($args) < 2) {
    fwrite(STDERR, "Usage: {$argv[0]} junit.xml... out.json\n");
    exit(1);
}
$output = array_pop($args);
$junitFiles = $args;

/** @var array<string, list<string>> */
$map = [];

foreach ($junitFiles as $jf) {
    $path = str_starts_with($jf, '/') ? $jf : $root . '/' . $jf;
    if (!is_readable($path)) {
        continue;
    }
    $xml = simplexml_load_file($path);
    if ($xml === false) {
        continue;
    }
    foreach ($xml->testsuite as $top) {
        collectCases($top, $map, $path);
    }
}

$out = ['entries' => [], 'source_files' => $junitFiles];
foreach ($map as $testClass => $targets) {
    $out['entries'][] = [
        'test_class' => $testClass,
        'candidate_symbols' => array_values(array_unique($targets)),
    ];
}

$dir = dirname($output);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
file_put_contents($output, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
fwrite(STDERR, 'Wrote ' . count($out['entries']) . " test mappings to {$output}\n");

/**
 * @param array<string, list<string>> $map
 */
function collectCases(SimpleXMLElement $suite, array &$map, string $source): void
{
    foreach ($suite->testcase as $case) {
        $class = (string) ($case['class'] ?? '');
        if ($class === '') {
            continue;
        }
        $targets = guessTargets($class);
        if (!isset($map[$class])) {
            $map[$class] = [];
        }
        foreach ($targets as $t) {
            $map[$class][] = $t;
        }
    }
    foreach ($suite->testsuite as $child) {
        collectCases($child, $map, $source);
    }
}

/**
 * @return list<string>
 */
function guessTargets(string $testClass): array
{
    if (!str_contains($testClass, '\\Tests\\')) {
        return [];
    }
    $parts = explode('\\', $testClass);
    $vendor = $parts[0] ?? '';
    if ($vendor !== 'Waaseyaa') {
        return [];
    }
    $idx = array_search('Tests', $parts, true);
    if ($idx === false || $idx < 2) {
        return [];
    }
    $pkg = $parts[1];
    $after = array_slice($parts, $idx + 1);
    $candidates = [];
    if ($after !== []) {
        $last = end($after);
        if (is_string($last) && str_ends_with($last, 'Test')) {
            $base = substr($last, 0, -4);
            $candidates[] = "{$vendor}\\{$pkg}\\{$base}";
        }
    }
    $candidates[] = "{$vendor}\\{$pkg}\\*";

    return $candidates;
}
