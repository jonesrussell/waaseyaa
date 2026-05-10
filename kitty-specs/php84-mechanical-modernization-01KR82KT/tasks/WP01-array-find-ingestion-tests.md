---
work_package_id: WP01
title: 'array_find: ingestion test suites'
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-php84-mechanical-modernization-01KR82KT
base_commit: d7d5270dea54a7ae309e7492c70a979a94715f5c
created_at: '2026-05-10T04:45:04.418208+00:00'
subtasks:
- T001
- T002
- T003
- T004
shell_pid: "86581"
agent: "claude:opus-4-7:opus-implementer:implementer"
history:
- timestamp: '2026-05-10T04:40:07Z'
  actor: spec-kitty.tasks
  note: Initial work package generated from plan.md
authoritative_surface: packages/
execution_mode: code_change
owned_files:
- packages/cli/tests/Unit/Ingestion/SchemaValidatorTest.php
- packages/cli/tests/Unit/Ingestion/ValidationGateValidatorTest.php
- packages/cli/tests/Unit/Command/IngestRunCommandTest.php
- packages/foundation/tests/Unit/Ingestion/PayloadValidatorTest.php
tags: []
---

# WP01 — array_find: ingestion test suites

## Objective

Replace `array_values(array_filter(...))[0]` first-match patterns with `array_find()` across four ingestion test files (10 sites total). PHP 8.4 stdlib swap; behavior-preserving for tests that assert the matched element exists.

## Branch Strategy

- **Planning base branch**: `main`
- **Final merge target**: `main`
- Execution lane assignment is computed by `finalize-tasks` and recorded in `lanes.json`. The agent enters the worktree printed by `spec-kitty agent action implement WP01 --agent <name>`.

## Context

Audit on 2026-05-10 identified 10 first-match extraction sites in ingestion test suites. Each follows the same shape:

```php
$x = array_values(array_filter($source, fn ($e) => $predicate($e)))[0] ?? null;
```

PHP 8.4 introduces `array_find()` which encodes this exact pattern:

```php
$x = array_find($source, static fn ($e) => $predicate($e));
```

Returns the first element matching the predicate, or `null` if none match. **The `null` semantics are NOT identical to `array_values(array_filter)[0]` without `?? null`** — the prior pattern raised `Undefined offset`. Verify each site's no-match path before swapping.

## Subtasks

### T001 — `packages/cli/tests/Unit/Ingestion/SchemaValidatorTest.php` (10 sites)

**Lines**: 81, 103, 124, 144, 166, 186, 208, 230, 250, 275

**Steps**:
1. Open the file. For each line above:
   - Read 5–10 lines of surrounding context to understand the predicate and how the result is consumed.
   - Replace the `array_values(array_filter(...))[0]` block with `array_find(...)` while preserving the predicate verbatim (including `static` if present, parameter type hint, return type).
   - If the prior code had `?? null`, the swap is direct. If it did not, confirm a non-null assertion follows immediately (`assertNotNull`, `expect`, etc.) — `array_find` returning `null` will trip that assertion identically to an offset error.
2. Re-run only this file's tests:
   ```bash
   ./vendor/bin/phpunit packages/cli/tests/Unit/Ingestion/SchemaValidatorTest.php
   ```
3. Run `composer cs-fix packages/cli/tests/Unit/Ingestion/SchemaValidatorTest.php` (or the equivalent targeted Pint/CS-Fixer invocation) to keep the diff style-clean.

**Validation**:
- [ ] All 10 swap sites edited.
- [ ] `array_values(array_filter(` no longer appears in the file (verify with `rg`).
- [ ] PHPUnit for this file is green.
- [ ] No formatting noise introduced.

### T002 — `packages/foundation/tests/Unit/Ingestion/PayloadValidatorTest.php:618`

**Steps**:
1. Read lines 610–640 to understand the helper function this lives inside.
2. Apply the same replacement pattern as T001.
3. Re-run: `./vendor/bin/phpunit packages/foundation/tests/Unit/Ingestion/PayloadValidatorTest.php`.

**Validation**:
- [ ] Site swapped, file is green.

### T003 — `packages/cli/tests/Unit/Ingestion/ValidationGateValidatorTest.php:80`

**Steps**:
1. Read lines 70–95 (the `$mismatch = ...` extraction block).
2. Replace with `array_find`.
3. Re-run: `./vendor/bin/phpunit packages/cli/tests/Unit/Ingestion/ValidationGateValidatorTest.php`.

**Validation**:
- [ ] Site swapped, file is green.

### T004 — `packages/cli/tests/Unit/Command/IngestRunCommandTest.php:256`

**Steps**:
1. Read lines 245–275 (the `$duplicate = ...` extraction block).
2. Replace with `array_find`.
3. Re-run: `./vendor/bin/phpunit packages/cli/tests/Unit/Command/IngestRunCommandTest.php`.

**Validation**:
- [ ] Site swapped, file is green.

## Definition of Done

- [ ] All four test files modified; `array_values(array_filter(` count for these files = 0.
- [ ] `./vendor/bin/phpunit` (full suite) is green.
- [ ] `composer phpstan` reports no new errors.
- [ ] `composer cs-check` is green.
- [ ] Diff is reviewable in <5 minutes; no incidental changes.

## Risks

- **`array_find` returns `null` on miss**, prior code raised `Undefined offset`. Most test sites assert non-null next-line; verify per site.
- **PHP-CS-Fixer reformatting** could expand the diff. Run `composer cs-fix` only on the four edited files.
- **Predicate captures**: ensure closure captures (`use ($var)`) are preserved verbatim.

## Reviewer guidance

- Diff should be a clean `array_values(array_filter(X, P))[0]` → `array_find(X, P)` swap, nothing else.
- Confirm no production source files appear in the diff.
- Confirm `array_find` is used (not `array_filter` alone — that wouldn't compile against the call site shape).

## Implementation command

```bash
spec-kitty agent action implement WP01 --agent <agent-name>
```

## Activity Log

- 2026-05-10T04:45:05Z – claude:opus-4-7:opus-implementer:implementer – shell_pid=86581 – Assigned agent via action command
