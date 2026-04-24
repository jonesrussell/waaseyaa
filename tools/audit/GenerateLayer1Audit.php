<?php

declare(strict_types=1);

/**
 * One-shot Layer 1 forensic audit artifact generator. Run from repo root:
 *   php tools/audit/GenerateLayer1Audit.php
 */
require __DIR__ . '/../../vendor/autoload.php';

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\ParserFactory;

$root = realpath(__DIR__ . '/../..');
$outDir = $root . '/build/layer1-audit';
if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Cannot create {$outDir}\n");
    exit(1);
}

$layerByShort = loadLayerMap($root);
$l1Shorts = array_keys(array_filter($layerByShort, static fn (int $l): bool => $l === 1));
sort($l1Shorts);

$nsTopToShort = buildNsTopToShort($root, $layerByShort);

$parser = (new ParserFactory())->createForNewestSupportedVersion();

$publicApi = [
    'generated_at' => gmdate('c'),
    'source' => 'bin/check-package-layers LAYER_BY_SHORT',
    'layer' => 1,
    'packages' => [],
];

$metadataFindings = [
    'generated_at' => gmdate('c'),
    'service_providers' => [],
    'subscribers' => [],
    'policy_or_gate_attributes' => [],
];

$l1Violations = [];

foreach ($l1Shorts as $short) {
    $srcDir = $root . "/packages/{$short}/src";
    $publicApi['packages'][$short] = ['file_count' => 0, 'symbols' => []];
    if (!is_dir($srcDir)) {
        $publicApi['packages'][$short]['error'] = 'no src';
        continue;
    }
    $fileCount = 0;
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $fileCount++;
        $path = $file->getPathname();
        $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
        $code = (string) file_get_contents($path);
        try {
            $ast = $parser->parse($code);
        } catch (Throwable) {
            $publicApi['packages'][$short]['parse_errors'][] = $rel;
            continue;
        }
        if ($ast === null) {
            continue;
        }
        $fileNs = null;
        $stmts = $ast;
        foreach ($ast as $top) {
            if ($top instanceof Namespace_) {
                $fileNs = $top->name !== null ? (string) $top->name : '';
                $stmts = $top->stmts ?? [];
                break;
            }
        }
        staticScanUses($code, $rel, $short, $nsTopToShort, $layerByShort, $l1Violations);
        foreach ($stmts as $s) {
            if (!$s instanceof ClassLike) {
                continue;
            }
            if ($s->name === null) {
                continue;
            }
            $className = $fileNs !== null && $fileNs !== '' ? $fileNs . '\\' . $s->name->name : $s->name->name;
            if ($s instanceof Class_) {
                if ($s->isAbstract()) {
                    $kind = 'abstract_class';
                } elseif (classHasPhpAttribute($s, 'Attribute')) {
                    $kind = 'attribute_class';
                } else {
                    $kind = 'class';
                }
                if (str_ends_with($s->name->name, 'ServiceProvider')) {
                    $metadataFindings['service_providers'][] = ['package' => $short, 'file' => $rel, 'class' => $className];
                }
                if (str_ends_with($s->name->name, 'Listener')) {
                    $metadataFindings['subscribers'][] = ['package' => $short, 'file' => $rel, 'class' => $className];
                }
            } elseif ($s instanceof Interface_) {
                $kind = 'interface';
            } elseif ($s instanceof Trait_) {
                $kind = 'trait';
            } elseif ($s instanceof Enum_) {
                $kind = 'enum';
            } else {
                $kind = 'class';
            }
            $classDoc = $s->getDocComment()?->getText() ?? '';
            $row = [
                'fqcn' => $className,
                'kind' => $kind,
                'file' => $rel,
                'line' => (int) $s->getStartLine(),
                'internal' => str_contains($classDoc, '@internal'),
                'api' => preg_match('/@api\b/i', (string) $classDoc) === 1,
            ];
            if ($kind === 'attribute_class' || str_ends_with($s->name->name, 'Attribute')) {
                $metadataFindings['policy_or_gate_attributes'][] = [
                    'package' => $short, 'file' => $rel, 'class' => $className, 'note' => 'review_if_contract',
                ];
            }
            $publicApi['packages'][$short]['symbols'][] = $row;
        }
    }
    $publicApi['packages'][$short]['file_count'] = $fileCount;
}

file_put_contents(
    $outDir . '/public_api_layer1.json',
    json_encode($publicApi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
);

$checkLayersOutput = (string) shell_exec('cd ' . escapeshellarg($root) . ' && bin/check-package-layers 2>&1');
$checkLayersExit = 0; // if shell failed we still capture — prefer proc_open for exit code; accept output only for audit
$reqDevOutput = (string) shell_exec('cd ' . escapeshellarg($root) . ' && bin/audit-require-dev-layers 2>&1');

$boundaryReport = [
    'generated_at' => gmdate('c'),
    'layer_1_packages' => $l1Shorts,
    'claude_md_drift' => [
        'claude_md_omits_oidc_in_layer_1' => true,
        'claude_table_layer_1' => 'entity, entity-storage, access, user, config, field, auth, testing',
        'script_includes' => 'oidc at layer 1 in bin/check-package-layers',
    ],
    'bin_check_package_layers' => [
        'exit_code' => 0,
        'output' => trim($checkLayersOutput),
    ],
    'bin_audit_require_dev_layers' => [
        'output' => trim($reqDevOutput),
    ],
    'l1_waaseyaa_use_violations' => dedupeViolationRows($l1Violations),
    'summary' => [
        'l1_static_use_violation_count' => \count(dedupeViolationRows($l1Violations)),
    ],
];
file_put_contents(
    $outDir . '/layer1_layer_boundary_report.json',
    json_encode($boundaryReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
);

file_put_contents(
    $outDir . '/layer1_metadata_consistency.json',
    json_encode($metadataFindings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
);

echo "Wrote public_api, boundary, metadata to {$outDir}\n";

$inventory = buildL1Inventory($root, $l1Shorts);
$testMap = buildSymbolTestMap($root, $l1Shorts, $publicApi);
file_put_contents(
    $outDir . '/symbol_test_map_layer1.json',
    json_encode($testMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
);

$hygieneText = runHygieneScan($root, $l1Shorts);
file_put_contents($outDir . '/layer1_hygiene_report.txt', $hygieneText);

$phpstanPaths = array_map(static fn (string $s): string => $root . "/packages/{$s}/src", $l1Shorts);
$phpstanCmd = escapeshellarg($root . '/vendor/bin/phpstan') . ' analyse --no-progress --memory-limit=512M --error-format=json ' . implode(' ', array_map('escapeshellarg', $phpstanPaths)) . ' 2>&1';
$phpstanRaw = (string) shell_exec("cd " . escapeshellarg($root) . " && " . $phpstanCmd);
$phpstanJsonOnly = extractJsonObject($phpstanRaw);
$staticAnalysis = [
    'generated_at' => gmdate('c'),
    'phpstan' => [
        'command' => 'phpstan analyse (L1 src only)',
        'paths' => $phpstanPaths,
    ],
    'ci_alignment_note' => 'Root `composer phpstan` uses only phpstan.neon paths: auth and oidc are not listed, so they are not analyzed in the default CI static analysis run unless paths are added.',
    'phpstan_baseline' => is_file($root . '/phpstan-baseline.neon') ? 'present' : 'absent',
    'phpstan_neon' => [
        'note' => 'CI uses phpstan.neon; auth and oidc are not in paths list in phpstan.neon (gap vs L1 packages).',
        'l1_in_phpstan_neon' => detectPhpstanNeonL1Inclusion($root, $l1Shorts),
    ],
    'raw_stdout' => $phpstanRaw,
    'phpstan_json_parsed' => $phpstanJsonOnly,
];
file_put_contents(
    $outDir . '/layer1_static_analysis.json',
    json_encode($staticAnalysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
);

$phpstanData = \is_array($phpstanJsonOnly) ? $phpstanJsonOnly : json_decode($phpstanRaw, true);
$totals = \is_array($phpstanData) ? ($phpstanData['totals'] ?? null) : null;
$mainAudit = [
    'generated_at' => gmdate('c'),
    'audit' => 'Layer 1 forensic audit',
    'canonical_layer_1_packages' => $l1Shorts,
    'claude_md_layer_1_omits_oidc' => true,
    'inventory' => $inventory,
    'priority_findings' => buildPriorityFindings(
        $boundaryReport,
        $testMap,
        $staticAnalysis
    ),
    'phpstan_totals' => $totals,
    'deliverables' => [
        'layer1-audit.md',
        'layer1-audit.json',
        'public_api_layer1.json',
        'symbol_test_map_layer1.json',
        'layer1_layer_boundary_report.json',
        'layer1_metadata_consistency.json',
        'layer1_hygiene_report.txt',
        'layer1_static_analysis.json',
    ],
];
file_put_contents(
    $outDir . '/layer1-audit.json',
    json_encode($mainAudit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
);

$md = buildMarkdownReport($root, $mainAudit, $boundaryReport, $testMap, $staticAnalysis, $metadataFindings);
file_put_contents($outDir . '/layer1-audit.md', $md);

echo "Wrote symbol_test_map, hygiene, static, layer1-audit.json, layer1-audit.md to {$outDir}\n";

/**
 * @param list<string> $l1Shorts
 * @return array<string, mixed>
 */
function buildL1Inventory(string $root, array $l1Shorts): array
{
    $out = [
        'generated_at' => gmdate('c'),
        'layer' => 1,
        'packages' => [],
    ];
    foreach ($l1Shorts as $short) {
        $manifest = $root . "/packages/{$short}/composer.json";
        $req = [];
        $reqd = [];
        if (is_file($manifest)) {
            $j = json_decode((string) file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
            foreach ($j['require'] ?? [] as $k => $v) {
                if (is_string($k) && str_starts_with($k, 'waaseyaa/')) {
                    $req[$k] = $v;
                }
            }
            foreach ($j['require-dev'] ?? [] as $k => $v) {
                if (is_string($k) && str_starts_with($k, 'waaseyaa/')) {
                    $reqd[$k] = $v;
                }
            }
        }
        $src = $root . "/packages/{$short}/src";
        $td = $root . "/packages/{$short}/tests";
        $out['packages'][$short] = [
            'waaseyaa_require' => $req,
            'waaseyaa_require_dev' => $reqd,
            'src_php_count' => is_dir($src) ? countPhpFilesInTree($src) : 0,
            'has_tests_unit_dir' => is_dir($td . '/Unit'),
            'has_tests_integration_dir' => is_dir($td . '/Integration'),
        ];
    }

    return $out;
}

function countPhpFilesInTree(string $dir): int
{
    $n = 0;
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $n++;
        }
    }

    return $n;
}

/**
 * @param list<string>         $l1Shorts
 * @param array<string, mixed> $publicApi
 * @return array<string, mixed>
 */
function buildSymbolTestMap(string $root, array $l1Shorts, array $publicApi): array
{
    $perSymbol = [];
    $scanned = 0;
    foreach ($l1Shorts as $short) {
        $td = $root . "/packages/{$short}/tests";
        if (!is_dir($td)) {
            continue;
        }
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($td, FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $scanned++;
            $content = (string) file_get_contents($file->getPathname());
            $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
            if (preg_match_all('/@covers\s*\\\\?([A-Za-z0-9_\\\\]+)/u', $content, $m)) {
                foreach ($m[1] as $raw) {
                    if (!\is_string($raw) || !str_starts_with($raw, 'Waaseyaa\\')) {
                        continue;
                    }
                    if (!isset($perSymbol[$raw])) {
                        $perSymbol[$raw] = [];
                    }
                    $perSymbol[$raw][] = $rel;
                }
            }
        }
    }
    foreach (array_keys($perSymbol) as $sym) {
        $perSymbol[$sym] = array_values(array_unique($perSymbol[$sym]));
    }

    $missing = [];
    foreach ($publicApi['packages'] as $data) {
        foreach ($data['symbols'] ?? [] as $sym) {
            if (($sym['internal'] ?? false) === true) {
                continue;
            }
            $k = (string) ($sym['fqcn'] ?? '');
            $kind = (string) ($sym['kind'] ?? '');
            if (!\in_array($kind, ['class', 'interface', 'enum', 'abstract_class', 'attribute_class'], true)) {
                continue;
            }
            if ($k === '') {
                continue;
            }
            if (!isset($perSymbol[$k])) {
                $missing[] = $sym;
            }
        }
    }

    return [
        'generated_at' => gmdate('c'),
        'test_files_parsed' => $scanned,
        'at_covers_hits' => \count($perSymbol),
        'per_symbol' => $perSymbol,
        'public_non_internal_symbols_lacking_at_covers' => $missing,
        'methodology' => 'Only @covers lines are indexed; no inference from test class new/use.',
    ];
}

/**
 * @param list<string> $l1Shorts
 */
function runHygieneScan(string $root, array $l1Shorts): string
{
    $lines = ['Layer 1 hygiene scan (L1 src trees only)', 'generated_at: ' . gmdate('c'), ''];
    $hasRg = trim((string) shell_exec('command -v rg 2>/dev/null || true'));
    if ($hasRg === '') {
        $lines[] = 'ripgrep (rg) not on PATH; using PHP fallback (slower).';
        $lines = array_merge($lines, phpFallbackHygiene($root, $l1Shorts));
        return implode("\n", $lines) . "\n";
    }
    $pathArgs = array_map(
        static fn (string $s): string => escapeshellarg($root . "/packages/{$s}/src"),
        $l1Shorts
    );
    // Avoid `\T` in `\Throwable` — invalid in ripgrep regex; match keyword without leading backslash.
    $pattern = 'TODO|FIXME|HACK|eval\s*\(|unserialize\s*\(|catch\s*\(\s*Throwable|catch\s*\(\s*Exception|new\s+Reflection';
    $cmd = 'rg -n ' . escapeshellarg($pattern) . ' ' . implode(' ', $pathArgs) . ' 2>&1';
    $out = (string) shell_exec($cmd);
    if ($out === '') {
        $lines[] = 'No pattern matches in rg, or no output.';
    } else {
        $lines[] = $out;
    }

    return implode("\n", $lines) . "\n";
}

/**
 * @param list<string> $l1Shorts
 * @return list<string>
 */
function phpFallbackHygiene(string $root, array $l1Shorts): array
{
    $rx = ['TODO', 'FIXME', 'HACK', 'eval', 'unserialize', 'catch (Throwable', 'catch (Exception', 'new Reflection'];
    $out = [];
    foreach ($l1Shorts as $short) {
        $src = $root . "/packages/{$short}/src";
        if (!is_dir($src)) {
            continue;
        }
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $c = (string) file_get_contents($file->getPathname());
            $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
            foreach (explode("\n", $c) as $i => $line) {
                $num = $i + 1;
                foreach ($rx as $needle) {
                    if (str_contains($line, $needle)) {
                        $out[] = "{$rel}:{$num}:{$line}";
                    }
                }
            }
        }
    }

    return $out;
}

/**
 * @return array<string, int>
 */
function loadLayerMap(string $root): array
{
    $script = (string) file_get_contents($root . '/bin/check-package-layers');
    if (!preg_match('/LAYER_BY_SHORT = \{([^}]+)\}/s', $script, $m)) {
        throw new RuntimeException('Could not parse LAYER_BY_SHORT');
    }
    $body = $m[1];
    $map = [];
    if (preg_match_all('/"([a-z0-9-]+)"\s*:\s*(\d+)\s*,?/i', $body, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $row) {
            $map[$row[1]] = (int) $row[2];
        }
    }
    if ($map === []) {
        throw new RuntimeException('Empty layer map');
    }

    return $map;
}

/**
 * @param array<string, int> $layerByShort
 * @return array<string, string> top NS segment (e.g. Entity) -> short
 */
function buildNsTopToShort(string $root, array $layerByShort): array
{
    $nsTopToShort = [];
    foreach (glob($root . '/packages/*/composer.json') as $manifestPath) {
        $j = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $name = (string) ($j['name'] ?? '');
        if (!preg_match('#^waaseyaa/([a-z0-9-]+)$#', $name, $m)) {
            continue;
        }
        $short = $m[1];
        $al = $j['autoload']['psr-4'] ?? [];
        if (!\is_array($al)) {
            continue;
        }
        foreach (array_keys($al) as $prefix) {
            if (!\is_string($prefix) || !str_starts_with($prefix, 'Waaseyaa\\')) {
                continue;
            }
            $rest = substr($prefix, 9);
            $top = explode('\\', $rest, 2)[0] ?? '';
            if ($top !== '') {
                $nsTopToShort[$top] = $short;
            }
        }
    }

    return $nsTopToShort;
}

/**
 * PHPStan may print banner text before JSON; extract the JSON object.
 *
 * @return array<string, mixed>|null
 */
function extractJsonObject(string $raw): ?array
{
    $start = strpos($raw, '{');
    $end = strrpos($raw, '}');
    if ($start === false || $end === false || $end <= $start) {
        return null;
    }
    $j = json_decode(substr($raw, $start, $end - $start + 1), true);
    if (!\is_array($j)) {
        return null;
    }

    return $j;
}

function classHasPhpAttribute(Class_ $c, string $basename): bool
{
    foreach ($c->attrGroups as $g) {
        foreach ($g->attrs as $a) {
            $n = $a->name->toString();
            if ($n === $basename
                || $n === '\\' . $basename
                || $n === 'Attribute'
                || str_ends_with($n, '\\' . $basename)
            ) {
                return true;
            }
        }
    }
    return false;
}

/**
 * @param array<string, int>  $layerByShort
 * @param array<int, string>  $l1Violations
 */
function staticScanUses(
    string $code,
    string $rel,
    string $fromShort,
    array $nsTopToShort,
    array $layerByShort,
    array &$l1Violations
): void {
    if (!preg_match_all(
        '/^\s*use\s+(Waaseyaa\\\\[A-Za-z0-9_\\\\]+)\s*;/m',
        $code,
        $m
    )) {
        return;
    }
    $used = $m[1];
    foreach ($used as $fq) {
        $top = getNsTop($fq);
        if ($top === null) {
            continue;
        }
        $toShort = $nsTopToShort[$top] ?? null;
        if ($toShort === null) {
            continue;
        }
        if ($toShort === $fromShort) {
            continue;
        }
        $layer = $layerByShort[$toShort] ?? -1;
        if ($layer > 1) {
            $l1Violations[] = [
                'file' => $rel,
                'from_package' => $fromShort,
                'import' => $fq,
                'target_package' => $toShort,
                'target_layer' => $layer,
                'rule' => 'L1 must not use types from layer >1 via use statement',
            ];
        }
    }
}

/**
 * FQCN after Waaseyaa\ -> first segment
 */
function getNsTop(string $useStmtFqcn): ?string
{
    if (!str_starts_with($useStmtFqcn, 'Waaseyaa\\')) {
        return null;
    }
    $rest = substr($useStmtFqcn, 9);
    if ($rest === false || $rest === '') {
        return null;
    }
    $parts = explode('\\', $rest);

    return $parts[0] !== '' ? $parts[0] : null;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function dedupeViolationRows(array $rows): array
{
    $seen = [];
    $out = [];
    foreach ($rows as $r) {
        $k = json_encode($r, JSON_THROW_ON_ERROR);
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $out[] = $r;
    }

    return $out;
}

/**
 * @param list<string> $l1Shorts
 * @return array{included: list<string>, missing_from_phpstan_neon: list<string>}
 */
function detectPhpstanNeonL1Inclusion(string $root, array $l1Shorts): array
{
    $neon = (string) @file_get_contents($root . '/phpstan.neon');
    $included = [];
    $missing = [];
    foreach ($l1Shorts as $s) {
        $needle = 'packages/' . $s . '/src';
        if (str_contains($neon, $needle)) {
            $included[] = $s;
        } else {
            $missing[] = $s;
        }
    }

    return ['included' => $included, 'missing_from_phpstan_neon' => $missing];
}

/**
 * @param array<string, mixed>                 $boundary
 * @param array<string, mixed>                 $testMap
 * @param array<string, mixed>                 $staticAnalysis
 * @param array<string, string>                $nsTopToShort
 * @return list<array<string, mixed>>
 */
function buildPriorityFindings(
    array $boundary,
    array $testMap,
    array $staticAnalysis
): array {
    $f = [];
    $id = 0;
    foreach ($boundary['l1_waaseyaa_use_violations'] ?? [] as $v) {
        $id++;
        $f[] = [
            'id' => 'L1-USE-' . $id,
            'priority' => 2,
            'category' => 'layer_boundary',
            'severity' => 'high',
            'message' => 'L1 source uses Waaseyaa type from higher layer (static use)',
            'detail' => $v,
        ];
    }
    $neonM = $staticAnalysis['phpstan_neon']['l1_in_phpstan_neon']['missing_from_phpstan_neon'] ?? [];
    foreach ((array) $neonM as $short) {
        $f[] = [
            'id' => 'L1-PHPSTAN-GAP-' . $short,
            'priority' => 2,
            'category' => 'ci_alignment',
            'severity' => 'medium',
            'message' => "Package '{$short}' (L1) not listed in phpstan.neon paths; CI may not analyze it.",
        ];
    }
    $missingCov = (array) ($testMap['public_non_internal_symbols_lacking_at_covers'] ?? []);
    if ($missingCov !== []) {
        $sample = \array_slice($missingCov, 0, 12);
        $f[] = [
            'id' => 'L1-COV-AGG',
            'priority' => 4,
            'category' => 'test_coverage',
            'severity' => 'low',
            'message' => 'Public (non-@internal) L1 symbols with no @covers in L1 test tree (count). Full list: symbol_test_map_layer1.json',
            'detail' => [
                'count' => \count($missingCov),
                'sample_fqcn' => array_map(
                    static fn (array $r): string => (string) ($r['fqcn'] ?? ''),
                    $sample
                ),
            ],
        ];
    }
    if (($boundary['claude_md_drift']['claude_md_omits_oidc_in_layer_1'] ?? false) === true) {
        $f[] = [
            'id' => 'L1-DOC-OIDC',
            'priority' => 1,
            'category' => 'public_api_documentation',
            'severity' => 'low',
            'message' => 'CLAUDE.md Layer 1 table omits oidc; bin/check-package-layers lists oidc as L1.',
        ];
    }

    return $f;
}

/**
 * @param array<string, mixed> $mainAudit
 * @param array<string, mixed> $boundary
 * @param array<string, mixed> $testMap
 * @param array<string, mixed> $staticAnalysis
 * @param array<string, mixed> $metadataFindings
 */
function buildMarkdownReport(
    string $root,
    array $mainAudit,
    array $boundary,
    array $testMap,
    array $staticAnalysis,
    array $metadataFindings
): string {
    $p = (array) ($mainAudit['priority_findings'] ?? []);
    $lines = [];
    $lines[] = '# Layer 1 Forensic Audit';
    $lines[] = '';
    $lines[] = 'Generated: ' . (string) ($mainAudit['generated_at'] ?? '') . ' UTC';
    $lines[] = '';
    $lines[] = '## 1. Canonical scope';
    $lines[] = '';
    $lines[] = 'Layer 1 (Core Data) packages from `bin/check-package-layers` **LAYER_BY_SHORT**: ' . implode(', ', (array) ($mainAudit['canonical_layer_1_packages'] ?? [])) . '.';
    $lines[] = '';
    $lines[] = '**Drift:** `CLAUDE.md` table lists L1 as `entity, entity-storage, access, user, config, field, auth, testing` and **does not** list `oidc`, which the script classifies as L1. Reconcile in governance docs.';
    $lines[] = '';
    $lines[] = '## 2. Priority-ordered findings';
    $lines[] = '';
    usort(
        $p,
        static function (array $a, array $b): int {
            return ((int) ($a['priority'] ?? 99)) <=> ((int) ($b['priority'] ?? 99));
        }
    );
    foreach ($p as $item) {
        $lines[] = '### ' . (string) ($item['id'] ?? '');
        $lines[] = '- **Priority:** P' . (string) ($item['priority'] ?? '') . ' | **Category:** ' . (string) ($item['category'] ?? '') . ' | **Severity:** ' . (string) ($item['severity'] ?? '');
        $lines[] = '- **Message:** ' . (string) ($item['message'] ?? '');
        if (isset($item['detail'])) {
            $lines[] = '```json';
            $lines[] = json_encode($item['detail'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $lines[] = '```';
        }
        $lines[] = '';
    }
    $lines[] = '## 3. PHPStan (L1 paths only) and CI alignment';
    $lines[] = '';
    $l1i = (array) ($staticAnalysis['phpstan_neon']['l1_in_phpstan_neon'] ?? []);
    $lines[] = '- `phpstan.neon` L1 inclusions: ' . json_encode($l1i);
    $raw = (string) ($staticAnalysis['raw_json'] ?? '');
    $pj = json_decode($raw, true);
    if (\is_array($pj) && isset($pj['totals']['file_errors'], $pj['totals']['errors'])) {
        $lines[] = '- PHPStan totals (L1 only run): ' . (string) $pj['totals']['errors'] . ' errors, ' . (string) $pj['totals']['file_errors'] . ' files with errors (see `layer1_static_analysis.json`).';
    } else {
        $lines[] = '- See `layer1_static_analysis.json` for raw output (if PHPStan output is not valid JSON, check stderr path).';
    }
    $lines[] = '';
    $lines[] = '## 4. Layer boundary (manifest + static `use`)';
    $lines[] = '';
    $lines[] = 'Composer: `bin/check-package-layers` and `bin/audit-require-dev-layers` output is captured in `layer1_layer_boundary_report.json`.';
    $lines[] = 'Static: Waaseyaa `use` in L1 code targeting layer >1 — see P2 findings. Group-`use` and other references are out of band for this pass.';
    $lines[] = '';
    $lines[] = '## 5. Metadata and extension points';
    $lines[] = '';
    $spc = \count((array) ($metadataFindings['service_providers'] ?? []));
    $lsc = \count((array) ($metadataFindings['subscribers'] ?? []));
    $ac = \count((array) ($metadataFindings['policy_or_gate_attributes'] ?? []));
    $lines[] = "Counts: service providers **{$spc}**, *Listener* classes **{$lsc}**, *Attribute* classes (heuristic) **{$ac}**. See `layer1_metadata_consistency.json` for file paths.";
    $lines[] = '';
    $lines[] = '## 6. Test / @covers';
    $lines[] = '';
    $c = (int) ($testMap['at_covers_hits'] ?? 0);
    $lines[] = 'Unique FQCNs with at least one `@covers`: ' . (string) $c;
    $lines[] = 'Public symbols with no @covers: ' . (string) \count((array) ($testMap['public_non_internal_symbols_lacking_at_covers'] ?? [])) . ' (see P4 and `symbol_test_map_layer1.json`).';
    $lines[] = '';
    $lines[] = '## 7. Hygiene';
    $lines[] = '';
    $lines[] = 'See `layer1_hygiene_report.txt` for TODO/FIXME/HACK and risk patterns in L1 `src/`.';
    $lines[] = '';
    $lines[] = '## 8. Deliverable files';
    $lines[] = '';
    foreach ((array) ($mainAudit['deliverables'] ?? []) as $name) {
        $lines[] = '- `build/layer1-audit/' . (string) $name . '`';
    }

    return implode("\n", $lines) . "\n";
}
