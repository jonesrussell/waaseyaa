---
work_package_id: WP03
title: '#[\Deprecated] attribute + json_validate audit close-out'
dependencies:
- WP01
requirement_refs:
- FR-006
- FR-007
- FR-008
- FR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T007
- T008
- T009
history:
- timestamp: '2026-05-10T04:40:07Z'
  actor: spec-kitty.tasks
  note: Initial work package generated from plan.md
authoritative_surface: packages/
execution_mode: code_change
owned_files:
- packages/queue/src/FailedJobRepository.php
- packages/cli/src/Handler/MigrateDefaultsHandler.php
- packages/cli/src/Handler/FixturePackRefreshHandler.php
tags: []
---

# WP03 — `#[\Deprecated]` attribute + json_validate audit close-out

## Objective

Three discrete jobs:

1. Promote `FailedJobRepository`'s `@deprecated` docblock to a PHP 8.4 `#[\Deprecated]` attribute (keep both during this transition release).
2. Audit FR-007/008/009 sites; close-with-rationale where the decoded value is consumed (per research.md Decision 3); swap to `json_validate()` only at sites that are pure validity gates with no decoded-value consumption.
3. Run final mission-level verification (full PHPUnit, PHPStan, cs-check, layer + composer policy) once WP01 + WP02 are also done.

## Branch Strategy

- **Planning base branch**: `main`
- **Final merge target**: `main`
- Parallel-safe with WP01. `lanes.json` will allocate a separate worktree.

## Context

### `#[\Deprecated]` attribute

`packages/queue/src/FailedJobRepository.php:10` carries `@deprecated Use FailedJobRepositoryInterface...` in the docblock. PHP 8.4 introduces `#[\Deprecated]` which surfaces in IDEs, PHPStan, Reflection — strictly more visible than the docblock alone.

### json_validate audit

The 2026-05-10 audit flagged three CLI sites as catch-only-suppress candidates for `json_validate`. Closer inspection during planning showed:

- `packages/cli/src/Handler/MigrateDefaultsHandler.php:236` — decodes JSON and **uses** the result (`$entries[] = $entry`). Not a swap candidate.
- `packages/cli/src/Handler/FixturePackRefreshHandler.php:41` — decodes JSON and **uses** the result (`is_array($decoded)` shape check, then field reads). Not a swap candidate.
- `PerformanceCompareCommand` — locate first; assess.

Per research.md **Decision 3**, sites that consume the decoded value retain `try { json_decode } catch`. Replacing them with `json_validate + json_decode` doubles the parse cost.

This WP therefore **closes** FR-007/008/009 with an explicit rationale rather than swapping them — except for `PerformanceCompareCommand` if (and only if) it turns out to be a pure validity gate.

## Subtasks

### T007 — Add `#[\Deprecated]` attribute on `FailedJobRepository`

**Steps**:
1. Open `packages/queue/src/FailedJobRepository.php`.
2. Identify the `class FailedJobRepository` declaration; the existing docblock ends at the line above it.
3. Insert immediately above the class line (between docblock close `*/` and `class ...`):
   ```php
   #[\Deprecated(
       message: "Use FailedJobRepositoryInterface with InMemoryFailedJobRepository or DatabaseFailedJobRepository instead",
       since: "0.1"
   )]
   ```
4. **Keep** the existing `@deprecated` docblock line — IDEs that don't yet read attributes still benefit.
5. Run `./vendor/bin/phpunit packages/queue/tests/`. Confirm no test promotes `E_USER_DEPRECATED` to an error.
6. Run `composer phpstan packages/queue/`. PHPStan should see the attribute and may emit deprecation notices at any callsite — note them in the WP close-out (do not silence).

**Validation**:
- [ ] Attribute present, docblock retained.
- [ ] Queue package tests green.
- [ ] No new PHPStan errors (deprecation **warnings** are expected and informative).

### T008 — Audit FR-007/008/009 close-out

**Steps**:
1. Re-confirm the planning observation by reading lines 220–245 of `packages/cli/src/Handler/MigrateDefaultsHandler.php` and lines 30–60 of `packages/cli/src/Handler/FixturePackRefreshHandler.php`. Verify:
   - The decoded value flows into a downstream collection or shape check.
   - The catch is not pure validity-only suppression.
2. Document the close-out in this WP's prompt closure under T008. Standard form:
   ```
   FR-007 (MigrateDefaultsHandler:236): CLOSED. Decoded $entry is appended to $entries; per research.md Decision 3, retain try/catch.
   FR-008 (FixturePackRefreshHandler:41): CLOSED. Decoded $decoded is shape-checked and consumed; retain try/catch.
   FR-009 (PerformanceCompareCommand): <see step 3>.
   ```
3. Locate `PerformanceCompareCommand`:
   ```bash
   find packages -iname 'PerformanceCompare*' -type f
   rg -nP "class PerformanceCompare" packages/
   ```
   - If not found: close FR-009 with "site not present in current codebase; possible audit reference to a removed file".
   - If found and is catch-only-suppress (decode discarded): apply the swap:
     ```php
     // before
     try { json_decode($s, true, 512, JSON_THROW_ON_ERROR); return true; }
     catch (\JsonException) { return false; }
     // after
     return json_validate($s);
     ```
   - If found and decoded value is consumed: close-with-rationale like FR-007/008.

**Validation**:
- [ ] All three FRs closed in the WP close-out (with rationale or with swap diff).
- [ ] If a swap was applied: targeted phpunit run green for the affected file.

### T009 — Final mission-level verification

**Run from repo root** (after WP01 and WP02 are merged into the mission lane):

```bash
./vendor/bin/phpunit                     # full suite
composer phpstan                         # static analysis
composer cs-check                        # code style
bin/check-package-layers                 # layer discipline
composer check-composer-policy           # composer manifest policy
```

**Validation**:
- [ ] All five exit zero.
- [ ] Output summaries (final lines: pass counts, file counts, "no errors", "no violations") captured in WP close-out.

## Definition of Done

- [ ] `FailedJobRepository` has `#[\Deprecated]` attribute and retained docblock.
- [ ] FR-007 / FR-008 closed with rationale; FR-009 closed (rationale or swap).
- [ ] Mission-level five-command verification all green.
- [ ] Diff is reviewable; no incidental changes outside `owned_files`.

## Risks

- **`#[\Deprecated]` cascading deprecations**: any test that constructs `FailedJobRepository` will emit a deprecation. PHPUnit's deprecation handling depends on `phpunit.xml` settings — if `failOnDeprecation` is true, the queue tests may need to be relaxed, but this is preferable to discover and address rather than ignore.
- **`PerformanceCompareCommand` not being where audit said**: file may have been moved/renamed since audit. Don't speculate — close with "not present" if the search fails.

## Reviewer guidance

- T007 diff: should be a 4-line attribute insertion only; no docblock removal.
- T008 closure: prose-only for the two confirmed-not-applicable sites is fine. Do **not** accept a diff that swaps `MigrateDefaultsHandler` or `FixturePackRefreshHandler` to `json_validate` — those are not eligible.
- T009: verification output must be from the final mission lane (post-WP01+WP02 merges), not from this WP's worktree alone.

## Implementation command

```bash
spec-kitty agent action implement WP03 --agent <agent-name>
```
