---
work_package_id: WP04
title: bin/check-getquery-bindings + baseline
dependencies: []
requirement_refs:
- FR-005
- FR-006
- FR-007
- FR-013
planning_base_branch: main
merge_target_branch: main
branch_strategy: "Planning/base branch: main\nMerge target: main\nExecution worktree is allocated by finalize-tasks per lanes.json.\nDo NOT create a branch manually — spec-kitty agent action implement WP04 handles it.\nNote: WP04 has no hard code dependency on WP01/WP02, but the baseline is most accurate\nafter WP01+WP02 land. If WP04 runs ahead, regenerate the baseline after WP01+WP02 merge.\n"
subtasks:
- T015
- T016
- T017
- T018
- T019
history:
- date: '2026-05-20T23:30:18Z'
  agent: claude:sonnet:tasks:tasks
  action: created
authoritative_surface: bin/
execution_mode: code_change
owned_files:
- bin/check-getquery-bindings
- tools/getquery-bindings-baseline.txt
- composer.json
- CLAUDE.md
- tests/Integration/Phase24/GetQueryBindingsGateTest.php
tags: []
---

# WP04 — bin/check-getquery-bindings + baseline

**Mission**: `access-fail-closed-completeness-01KS3RJT`
**Closes**: #1528
**Requirements**: FR-005, FR-006, FR-007, NFR-001, NFR-003, C-003, SC-003

## Objective

Implement `bin/check-getquery-bindings` — a PHP CLI tool that scans `packages/*/src/**/*.php` for unbound `getQuery()->...->execute()` callsites (no `setAccount()` or `accessCheck(false)` in the chain). Generate `tools/getquery-bindings-baseline.txt` capturing current offenders. Wire the gate into `composer verify` so new unbound callsites fail CI. Write an integration test proving the gate works. Document in `CLAUDE.md`.

This is modeled on the dead-code baseline (`phpstan-dead-code-baseline.neon` + `bin/check-dead-code`): capture known state, fail on new additions.

## Context

### Background: Three regressions shipped

Between alpha.181 and triage (2026-05-20), three callsites were fixed manually after production 500s:
- `PathAliasResolver` (#1518)
- `AuthController::findUserByName` (#1525)
- `SitemapGenerator` + `UserBlockService` (#1527)

The pattern — `$storage->getQuery()->condition(...)->execute()` without `setAccount()` or `accessCheck(false)` — had no static guard.

### Contract: see contracts/getquery-bindings-baseline-format.md

Read `kitty-specs/access-fail-closed-completeness-01KS3RJT/contracts/getquery-bindings-baseline-format.md` before coding. It specifies:
- Baseline file format (path:line + mandatory inline comment).
- Detection scope (packages/*/src/, not tests/).
- Three script modes: no-flags (verify), `--generate-baseline`, `--verify`.
- The exact `composer.json` additions.

### Baseline timing

WP04 can run before WP01+WP02 land. If it does, the baseline may include callsites that WP01+WP02 will fix. That is acceptable — but the WP author **must regenerate the baseline** after WP01+WP02 are approved so the baseline reflects the fixed state. The PR description should note this.

### Precedent

`bin/check-dead-code` and `phpstan-dead-code-baseline.neon` (landed in #1504) are the template. Study them before writing the new script.

## Branch Strategy

- Planning base: `main`
- Merge target: `main`
- No hard dependencies on WP01/WP02/WP03, but baseline should reflect their changes.
- Implement command: `spec-kitty agent action implement WP04 --agent <name>`

---

## Subtask T015 — Write `bin/check-getquery-bindings` PHP CLI scanner

**Purpose**: Detect unbound `getQuery()->...->execute()` chains in all production PHP files.

**File**: `bin/check-getquery-bindings`

**Detection logic** (key design decision):

The scanner must detect cases where a call chain starting with `getQuery()` reaches `->execute()` without `->setAccount(` or `->accessCheck(false)` in between. This is tricky across multi-line method chains. Recommended approach:

1. **Per-file, sliding-window scan**: When `getQuery()` is found on a line, collect that line and the next 10–15 lines (configurable), join them into a single string, then check if `->execute()` appears without `->setAccount(` or `->accessCheck(false)` in the collected string.

2. **Regex pattern** for the collected chain:
```php
// Detect: getQuery() appears, followed eventually by ->execute(), without setAccount or accessCheck(false)
$chain = implode(' ', array_slice($lines, $startLine, 15));
if (str_contains($chain, 'getQuery()') && str_contains($chain, '->execute()')) {
    if (!str_contains($chain, '->setAccount(') && !str_contains($chain, '->accessCheck(false)')) {
        // OFFENDER
    }
}
```

3. **Edge cases**:
   - `->accessCheck(true)` is NOT sufficient — only `false` explicitly opts out of access check. However, `->accessCheck(true)` + `->setAccount(...)` IS correct. So the rule is: fail if both `->setAccount(` AND `->accessCheck(false)` are absent.
   - Chains spread across more than 15 lines: unlikely in practice but may require tuning the window.
   - Variable assignments between lines: `$q = $storage->getQuery(); $q = $q->condition(...); $q->execute();` — these are harder to catch with a sliding window. For initial baseline, accept false negatives on split-variable patterns; the spec does not require AST-level analysis.

**Script structure**:

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

// Usage: php bin/check-getquery-bindings [--generate-baseline | --verify]

$mode = 'verify'; // default
foreach ($argv as $arg) {
    if ($arg === '--generate-baseline') { $mode = 'generate'; }
    if ($arg === '--verify') { $mode = 'verify'; }
}

$repoRoot = dirname(__DIR__);
$baselineFile = $repoRoot . '/tools/getquery-bindings-baseline.txt';
$scanDirs = glob($repoRoot . '/packages/*/src', GLOB_ONLYDIR) ?: [];

$offenders = findOffenders($scanDirs, $repoRoot);

if ($mode === 'generate') {
    generateBaseline($offenders, $baselineFile);
    echo "Generated baseline with " . count($offenders) . " entries.\n";
    exit(0);
}

// Verify mode
$baseline = loadBaseline($baselineFile);
$newOffenders = array_diff($offenders, $baseline);
if ($newOffenders !== []) {
    foreach ($newOffenders as $entry) {
        echo "NEW unbound getQuery()->execute() callsite: $entry\n";
    }
    echo count($newOffenders) . " new unbound callsite(s) found. Add to baseline with exemption comment or fix.\n";
    exit(1);
}

echo "check-getquery-bindings: OK (" . count($baseline) . " known exemptions, 0 new offenders)\n";
exit(0);
```

**Functions to implement**:
- `findOffenders(array $scanDirs, string $repoRoot): array` — walks each dir recursively, reads each `.php` file, applies sliding-window detection, returns `"path/relative/to/repo:lineNumber"` strings sorted by path then line.
- `loadBaseline(string $baselineFile): array` — reads the baseline file, strips comment lines (starting with `#`) and inline comments (`# ...` after the path:line), returns path:line strings.
- `generateBaseline(array $offenders, string $baselineFile)` — writes the baseline with header comment, generates a stub entry for each offender (requires manual addition of inline comment by author).

**Performance**: NFR-001 requires completion in under 30 seconds. `file()` + simple `str_contains()` on ~62 packages is well within budget. No need for `nikic/php-parser` (Assumption A4).

**Steps**:

1. Create `bin/check-getquery-bindings` (no `.php` extension — it is a CLI script).
2. Add shebang: `#!/usr/bin/env php`.
3. Make executable: `chmod +x bin/check-getquery-bindings`.
4. Implement the functions above.
5. Test manually: `php bin/check-getquery-bindings --verify` (before baseline exists, it should report all offenders and exit 1, or handle missing baseline gracefully by treating all offenders as new).

**Validation**:
- [ ] Script executes without fatal errors.
- [ ] `php bin/check-getquery-bindings --verify` exits 1 when a synthetic offender is present (see T018).
- [ ] `php bin/check-getquery-bindings --verify` exits 0 when all offenders are in the baseline.
- [ ] Completes under 30 seconds on the full repo (NFR-001): `time php bin/check-getquery-bindings --verify`.
- [ ] `composer cs-check` passes on the script file.

---

## Subtask T016 — Generate and commit `tools/getquery-bindings-baseline.txt`

**Purpose**: Capture the current set of unbound callsites as the baseline so CI permits them while blocking new additions (FR-006).

**File**: `tools/getquery-bindings-baseline.txt`

**Steps**:

1. Ensure `tools/` directory exists (it does — `tools/drift-detector.sh` already lives there).

2. Run the baseline generator:
```bash
php bin/check-getquery-bindings --generate-baseline
```

3. Review the generated `tools/getquery-bindings-baseline.txt`. For every entry, add an inline exemption comment explaining why the callsite is permitted:
   - System-context callers (sitemap, anonymous fallback): `# system-context: <reason>`
   - Pre-fix callsites that WP01/WP02 will fix: may be absent from baseline after those WPs land; if running ahead, note "# pre-WP01-fix: will be removed after WP01 merges".
   - Any entry WITHOUT an inline comment must be given one before committing (per contracts/getquery-bindings-baseline-format.md).

4. Verify the baseline format:
```bash
php bin/check-getquery-bindings --verify
```
Should exit 0 after generating the baseline.

5. Sort: entries must be sorted by `sort -t: -k1,1 -k2,2n`. The `generateBaseline` function should sort automatically; verify the output.

**Expected baseline content** (approximate — will differ based on current codebase state):
```
# Baseline: getQuery() bindings exempt from CI gate
# Format: <relative-path>:<line>  # <mandatory-exemption-reason>
# Generated by: php bin/check-getquery-bindings --generate-baseline
# Last generated: 2026-05-20
#
packages/path/src/PathAliasResolver.php:47  # system-context: no per-user alias ACL by design
packages/seo/src/SitemapGenerator.php:83    # system-context: sitemap is always anonymous
```
(Exact paths and line numbers will vary. If WP01 is not yet landed, `SearchController`-adjacent entries may also appear.)

**Validation**:
- [ ] `tools/getquery-bindings-baseline.txt` exists.
- [ ] Every non-comment line has an inline comment (`# <reason>`).
- [ ] File is plain text, UTF-8, LF line endings.
- [ ] `php bin/check-getquery-bindings --verify` exits 0 with this baseline.
- [ ] NFR-003: sorted by path then line.

---

## Subtask T017 — Wire gate into `composer verify`

**Purpose**: Make `bin/check-getquery-bindings` a mandatory step in every `composer verify` run (C-006, FR-007).

**File**: `composer.json`

**Steps**:

1. Add script definition in `composer.json`:
```json
"scripts": {
    "check-getquery-bindings": "php bin/check-getquery-bindings",
    "verify": [
        "@cs-check",
        "@phpstan",
        "@check-composer-policy",
        "@check-package-layers",
        "@check-no-secrets",
        "@check-ingestion-defaults",
        "@check-symfony-imports",
        "@check-dead-code",
        "@check-getquery-bindings",
        "@test"
    ]
}
```

2. Add description:
```json
"scripts-descriptions": {
    "check-getquery-bindings": "Fail-on-new gate for unbound getQuery()->execute() callsites. Baseline at tools/getquery-bindings-baseline.txt. New callsites fail CI; add to baseline with mandatory exemption comment if legitimately exempt. See CLAUDE.md § CI gates."
}
```

3. Verify the existing `verify` array order — `@check-getquery-bindings` should come after dead-code and before `@test`.

4. Run `composer verify` and confirm the gate step runs and exits 0.

**Validation**:
- [ ] `composer check-getquery-bindings` executes successfully.
- [ ] `composer verify` runs the new step and exits 0 on a clean checkout.
- [ ] `composer check-composer-policy` passes on the modified `composer.json`.

---

## Subtask T018 — Write `GetQueryBindingsGateTest` (SC-003)

**Purpose**: Automated test proving the gate correctly rejects a synthetic file containing an unbound callsite (SC-003).

**File**: `tests/Integration/Phase24/GetQueryBindingsGateTest.php`

**Namespace**: `Waaseyaa\Tests\Integration\Phase24`

**Steps**:

1. Create the test file.

2. Test design: the test creates a temp PHP fixture file containing an unbound `getQuery()->condition(...)->execute()` chain, then runs `bin/check-getquery-bindings` against a temp directory containing only that file. The script must exit non-zero.

3. Test structure:
```php
#[CoversNothing]
final class GetQueryBindingsGateTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/waaseyaa_gqbtest_' . uniqid();
        mkdir($this->tempDir . '/src', 0755, true);
    }

    protected function tearDown(): void
    {
        // Remove temp files
    }

    #[Test]
    public function unboundCallsiteFailsGate(): void
    {
        // Create a synthetic offender file in $this->tempDir/src/
        file_put_contents($this->tempDir . '/src/OffenderClass.php', '<?php
$storage->getQuery()->condition("status", 1)->execute();
');
        // Run the check script against the temp dir
        $scriptPath = dirname(__DIR__, 3) . '/bin/check-getquery-bindings';
        // Pass a custom scan path to the script (the script needs to support --scan-dir or env var)
        // Alternative: use --generate-baseline mode and check output
        exec("php {$scriptPath} --scan-dir {$this->tempDir}/src --baseline /dev/null 2>&1", $output, $exitCode);
        self::assertNotSame(0, $exitCode, 'Expected non-zero exit for unbound callsite');
    }

    #[Test]
    public function boundCallsitePassesGate(): void
    {
        file_put_contents($this->tempDir . '/src/BoundClass.php', '<?php
$storage->getQuery()->setAccount($account)->condition("status", 1)->execute();
');
        $scriptPath = dirname(__DIR__, 3) . '/bin/check-getquery-bindings';
        exec("php {$scriptPath} --scan-dir {$this->tempDir}/src --baseline /dev/null 2>&1", $output, $exitCode);
        self::assertSame(0, $exitCode, 'Expected zero exit for bound callsite');
    }
}
```

4. **If the script does not support `--scan-dir` / `--baseline` args**: add those flags to T015's implementation, or write the test differently (e.g. by creating a temp baseline that includes the offender, then verifying a new offender in a different temp dir is rejected). Make the test self-contained.

5. Alternative simpler approach: test the scanner functions directly by calling internal helper functions via `include`-ing the script after defining the fixture content. This avoids subprocess complexity.

**Validation**:
- [ ] `unboundCallsiteFailsGate` passes (exits non-zero).
- [ ] `boundCallsitePassesGate` passes (exits 0).
- [ ] `./vendor/bin/phpunit tests/Integration/Phase24/GetQueryBindingsGateTest.php` exits 0.

---

## Subtask T019 — Add "CI gates" section to `CLAUDE.md`

**Purpose**: Document the new CI gate so future contributors discover it and understand the baseline workflow (C-003).

**File**: `CLAUDE.md`

**Steps**:

1. Add a new section to `CLAUDE.md`. Good placement: after the "Dead code audits" section or in the "Commands" section, under a new `## CI Gates` heading.

2. Content:
```markdown
## CI Gates

Two CI gates run as part of `composer verify`.

### Dead code gate

`bin/check-dead-code` + `phpstan-dead-code-baseline.neon` — fails on new unreferenced symbols.
See the "Dead code audits" section above.

### Unbound getQuery() gate

`bin/check-getquery-bindings` — fails on new `getQuery()->...->execute()` callsites that have neither `->setAccount()` nor `->accessCheck(false)` in the call chain.

**Baseline file**: `tools/getquery-bindings-baseline.txt`

**Adding a new exemption**: Append `packages/foo/src/Bar.php:<line>  # <reason>` to the baseline and commit it in the same PR. Every entry **must** have an inline comment explaining why the callsite is exempt. Entries without a comment cause CI to fail.

**Regenerating the baseline** (after fixing a batch of callsites or after a rename):
```bash
php bin/check-getquery-bindings --generate-baseline
```
Review the output, add missing inline comments, and commit. The M-B.1 follow-up issue tracks driving this baseline to zero.

**What counts as "bound"**:
- `$storage->getQuery()->setAccount($account)->...->execute()` — bound, OK.
- `$storage->getQuery()->accessCheck(false)->...->execute()` — explicit opt-out, OK (add an inline justification comment in the source file).
- `$storage->getQuery()->...->execute()` with no binding — NEW offender, CI fails.
```

3. Run `composer cs-check` on the modified `CLAUDE.md` if the fixer touches it (it likely does not, but verify).

**Validation**:
- [ ] `CLAUDE.md` contains the "CI gates" section.
- [ ] The section documents both gates (dead-code and getquery-bindings).
- [ ] Instructions for adding an exemption and regenerating the baseline are present.
- [ ] `composer verify` still exits 0.

---

## Definition of Done

- [ ] `bin/check-getquery-bindings` exists, is executable, supports `--generate-baseline` and `--verify` flags.
- [ ] `tools/getquery-bindings-baseline.txt` exists, sorted, all entries have inline comments.
- [ ] `composer verify` runs the gate and exits 0 on a clean checkout.
- [ ] `GetQueryBindingsGateTest` passes.
- [ ] `CLAUDE.md` "CI gates" section added.
- [ ] `composer verify` exits 0.
- [ ] `Closes #1528` in the PR description.

## Risks

| Risk | Mitigation |
|---|---|
| Multi-line chain detection (false negatives) | Use sliding window of 15 lines; document known limitation; AST upgrade tracked by M-B.1 |
| Baseline entries without inline comments | Enforce in the script: missing comment → CI fails with specific error |
| WP04 runs before WP01+WP02 fix their callsites | Baseline may contain extra entries; must regenerate baseline after WP01+WP02 land; note in PR description |
| `--scan-dir` flag needed for test isolation | Add the flag to T015 if the test requires it |
| NFR-001 (30 s) exceeded on large monorepo | Benchmark with `time php bin/check-getquery-bindings`; optimize with early `str_contains` guard before sliding window |

## Reviewer Guidance

1. Confirm `bin/check-getquery-bindings` exits non-zero when a synthetic unbound callsite is present.
2. Confirm every baseline entry in `tools/getquery-bindings-baseline.txt` has an inline `# <reason>` comment.
3. Confirm the gate appears in `composer verify` script array (run `composer verify` and check output).
4. Run `time php bin/check-getquery-bindings --verify` — must complete under 30 seconds (NFR-001).
5. Confirm `CLAUDE.md` now has the CI gates section.
