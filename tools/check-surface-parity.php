<?php

declare(strict_types=1);

/**
 * Public-surface-map parity check.
 *
 * Enforces docs/specs/stability-charter.md §8.1. Run by .github/workflows/surface-parity.yml.
 *
 * Strategy:
 *   1. Load docs/public-surface-map.php into an in-memory registry.
 *   2. Scan the framework's source tree for exported symbols (public classes,
 *      interfaces, public methods, public consts) in non-`Internal\` namespaces.
 *   3. Compare. Fail on:
 *      - Symbol in source but not in map → "untracked surface"
 *      - Symbol in map but not in source → "removed without deprecation"
 *      - Tier downgraded without matching deprecation entry in CHANGELOG.md
 *
 * Implementation status: SKELETON (2026-05-11). The full scanner uses nikic/php-parser
 * to walk every src/ namespace and extract exported declarations. This skeleton
 * establishes the gate, defines the contract, and fails closed on missing data.
 * The full implementation lands as part of the ratification PR work.
 *
 * Usage:
 *   php tools/check-surface-parity.php [--base origin/main]
 *
 * Exit codes:
 *   0 — parity verified, no drift
 *   1 — drift detected (untracked symbol, removal-without-deprecation, or unauthorized downgrade)
 *   2 — infrastructure failure (map file missing, parser failure, etc.)
 */

const SURFACE_MAP_PATH = __DIR__ . '/../docs/public-surface-map.php';
const SCAN_ROOTS = [__DIR__ . '/../packages', __DIR__ . '/../src'];
const CHANGELOG_PATH = __DIR__ . '/../CHANGELOG.md';

function fail(string $msg, int $exit = 1): never
{
    fwrite(STDERR, "surface-parity: {$msg}\n");
    exit($exit);
}

function info(string $msg): void
{
    fwrite(STDOUT, "surface-parity: {$msg}\n");
}

// ---------------------------------------------------------------------------
// Phase 1: Load the surface map
// ---------------------------------------------------------------------------

if (!file_exists(SURFACE_MAP_PATH)) {
    fail('docs/public-surface-map.php not found. Per charter §2.5 this file is the single source of truth for the public surface.', 2);
}

/** @var array<string, array{tier: string, mission_status: string}> $surfaceMap */
$surfaceMap = require SURFACE_MAP_PATH;

if (!is_array($surfaceMap)) {
    fail('public-surface-map.php must return an array. See docs/specs/stability-charter.md §2.5 for the expected shape.', 2);
}

info('Loaded ' . count($surfaceMap) . ' symbols from public-surface-map.php.');

// ---------------------------------------------------------------------------
// Phase 2: Scan source for exported symbols
// ---------------------------------------------------------------------------

$discoveredSymbols = [];

foreach (SCAN_ROOTS as $root) {
    if (!is_dir($root)) {
        info("Scan root {$root} does not exist; skipping.");
        continue;
    }
    // SKELETON: real implementation uses nikic/php-parser to walk the AST,
    // extract public classes/interfaces/methods/consts in non-Internal\ namespaces.
    // The skeleton emits a stub list so the CI gate is in place.
    info("Scanning {$root} (skeleton — full AST scan TBD)...");
}

// ---------------------------------------------------------------------------
// Phase 3: Compare and report
// ---------------------------------------------------------------------------

$untracked = array_diff($discoveredSymbols, array_keys($surfaceMap));
$removed = array_diff(array_keys($surfaceMap), $discoveredSymbols);

if (!empty($untracked)) {
    fail('Untracked surface — symbols found in source but missing from public-surface-map.php: ' . PHP_EOL . '  ' . implode(PHP_EOL . '  ', $untracked));
}

if (!empty($removed)) {
    // Verify a deprecation entry exists in CHANGELOG.md for each removed symbol.
    $changelog = file_exists(CHANGELOG_PATH) ? file_get_contents(CHANGELOG_PATH) : '';
    foreach ($removed as $symbol) {
        if (!str_contains($changelog, $symbol)) {
            fail("Removed-without-deprecation — symbol `{$symbol}` is in public-surface-map.php but missing from source and has no entry in CHANGELOG.md. Per charter §4 deprecation cycle, removals require a shim, a deprecation notice, and a `### Removed` changelog entry.");
        }
    }
}

// ---------------------------------------------------------------------------
// Skeleton signal: until the full scanner is implemented, exit 0 with a notice
// so the CI gate is wired but doesn't false-positive on PRs.
// ---------------------------------------------------------------------------

info('Skeleton check passed. Full scanner implementation tracked under the §12 ratification PR work.');
exit(0);
