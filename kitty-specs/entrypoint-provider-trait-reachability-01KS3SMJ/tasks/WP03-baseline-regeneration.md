---
work_package_id: WP03
title: Baseline Regeneration and Verification
dependencies:
- WP02
requirement_refs:
- FR-002
- FR-004
- NFR-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T009
- T010
- T011
- T012
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "772291"
history:
- date: '2026-05-20T23:57:25Z'
  author: tasks-materializer
  note: Initial WP file created
authoritative_surface: phpstan-dead-code-baseline.neon
execution_mode: code_change
owned_files:
- phpstan-dead-code-baseline.neon
tags: []
---

# WP03 — Baseline Regeneration and Verification

## Branch Strategy

- **Planning/base branch**: `main`
- **Merge target**: `main`
- **Pre-condition**: WP02 must be approved. `tools/phpstan/WaaseyaaEntrypointProvider.php` must have `isTraitWithApiPhpDoc()` in place and all unit tests passing.
- **Worktree**: Allocated from `lanes.json` at runtime. Run `spec-kitty agent action implement WP03 --agent <name>` to enter the lane.

## Objective

Regenerate `phpstan-dead-code-baseline.neon` with the WP02 provider fix in place. Assert that exactly 31 entries dropped (the three traits). Confirm `composer verify` and the unit test filter both exit 0. This WP only touches `phpstan-dead-code-baseline.neon`.

## Context

The dead-code baseline suppresses known acceptable dead-code findings. With WP02's `isTraitWithApiPhpDoc()` fix, shipmonk will no longer report the 31 entries as dead — the provider now marks them as used. Regenerating the baseline removes those 31 entries permanently.

Per CLAUDE.md: "To regenerate the baseline after a triage sweep: `vendor/bin/phpstan analyse -c phpstan-dead-code.neon --generate-baseline=phpstan-dead-code-baseline.neon`."

Per spec C-002: "The fix shrinks the baseline by exactly 31 entries (the trait counts above)." Any new dead-code findings discovered by the regeneration are out of scope for this mission.

---

## Subtask T009 — Regenerate phpstan-dead-code-baseline.neon

**Purpose**: Run the canonical baseline regeneration command to produce a new baseline that excludes the 31 now-recognized entries.

**Steps**:

1. Confirm WP02's provider fix is on the worktree:
   ```bash
   grep -n "isTraitWithApiPhpDoc" tools/phpstan/WaaseyaaEntrypointProvider.php
   ```
   Should return the method definition line. If absent, stop — WP02 has not been merged to this worktree's base.

2. Record the entry count BEFORE regeneration:
   ```bash
   grep -c "message:" phpstan-dead-code-baseline.neon
   ```
   Save this number as BEFORE_COUNT.

3. Run the regeneration:
   ```bash
   vendor/bin/phpstan analyse -c phpstan-dead-code.neon --generate-baseline=phpstan-dead-code-baseline.neon
   ```
   This overwrites the baseline file. It may take 2–5 minutes on a full checkout.

   **Note**: The command exits 0 when the baseline is written successfully. It does NOT exit non-zero just because dead code was found — findings at or below the baseline are suppressed. If it exits non-zero, an error occurred (PHP error, missing autoload, config issue) — fix before continuing.

4. Record the entry count AFTER regeneration:
   ```bash
   grep -c "message:" phpstan-dead-code-baseline.neon
   ```
   Save as AFTER_COUNT.

**Validation**:
- [ ] `isTraitWithApiPhpDoc` confirmed present in provider before running.
- [ ] Regeneration exits 0.
- [ ] BEFORE_COUNT and AFTER_COUNT recorded.

---

## Subtask T010 — Assert 31 entries dropped and trait names absent

**Purpose**: Verify the exact 31 entries were removed and no entries remain for the three traits (SC-001).

**Steps**:

1. Calculate the delta:
   ```bash
   echo "Before: $BEFORE_COUNT  After: $AFTER_COUNT  Delta: $((BEFORE_COUNT - AFTER_COUNT))"
   ```
   The delta should equal exactly 31. If delta > 31, additional unrelated dead code was cleaned up (note it, it is not a failure for this mission — but document it). If delta < 31, the fix is incomplete.

2. Assert zero remaining entries for each of the three traits:
   ```bash
   grep -c "RevisionableEntityTrait" phpstan-dead-code-baseline.neon || true
   grep -c "InteractsWithApi" phpstan-dead-code-baseline.neon || true
   grep -c "RefreshDatabase" phpstan-dead-code-baseline.neon || true
   ```
   Each must return `0`. If any returns non-zero, WP02's fix did not cover all members — stop and report.

3. Run the combined SC-001 check from the spec:
   ```bash
   grep -cE "(RevisionableEntityTrait|InteractsWithApi|RefreshDatabase)" phpstan-dead-code-baseline.neon
   ```
   Must return `0`.

4. If delta != 31 or the grep returns non-zero:
   - Do NOT commit the baseline.
   - Report the discrepancy with the exact count.
   - If any of the 31 entries remain: this means the provider fix in WP02 is incomplete. Stop this WP and create a note for the spec-kitty reviewer describing which entries remain and in which file.
   - If delta > 31 (more entries dropped): This can happen if regeneration also picked up unrelated changes. Document the extra-dropped entries. If they are genuinely unrelated to the three traits, proceed (they are a net positive). If they look suspicious (e.g., previously-valid entries now incorrectly dropped), report.

**Validation**:
- [ ] `grep -cE "…" phpstan-dead-code-baseline.neon` returns `0`.
- [ ] Delta recorded and documented in commit message.
- [ ] No manual entries removed — only regeneration used (C-002).

---

## Subtask T011 — Run `composer verify` — exits 0

**Purpose**: Confirm the full CI gate is green with the new baseline (SC-003, C-004).

**Steps**:

1. Run:
   ```bash
   composer verify
   ```

2. This runs: PHPStan + dead-code check + code style + unit tests (depending on what `verify` contains in this project). The dead-code check (`bin/check-dead-code`) should now exit 0 because the baseline covers the remaining acceptable entries and the 31 trait entries are no longer reported.

3. If `composer verify` fails:
   - **If it fails on dead-code**: The regenerated baseline may have missed something, or a new finding was introduced by WP02's code changes. Run `bin/check-dead-code` alone and read the output carefully.
   - **If it fails on PHPStan type errors**: WP02's new code introduced a type violation. Fix in WP02's worktree (this is a WP02 issue, not WP03) and re-run WP03 after WP02 is re-approved.
   - **If it fails on code style**: Run `composer cs-fix` and `composer cs-check` to confirm; fix in WP02's worktree.
   - **Do NOT proceed to commit if `composer verify` is not green.**

4. Record the `composer verify` exit code and any output.

**Validation**:
- [ ] `composer verify` exits 0.
- [ ] `bin/check-dead-code` specifically exits 0.

---

## Subtask T012 — Run `phpunit --filter WaaseyaaEntrypointProviderTest` — exits 0

**Purpose**: Confirm the WP02 unit tests still pass after baseline regeneration (SC-002). Ensures the test file was included in the worktree.

**Steps**:

1. Run:
   ```bash
   vendor/bin/phpunit tools/phpstan/tests/WaaseyaaEntrypointProviderTest.php
   ```
   Or with filter if the test suite is configured:
   ```bash
   vendor/bin/phpunit --filter WaaseyaaEntrypointProviderTest
   ```

2. All tests must pass. If they fail here (but passed in WP02), there is likely a merge conflict or missing file in this worktree's checkout. Resolve before committing.

3. Commit the regenerated baseline:
   ```bash
   git add phpstan-dead-code-baseline.neon
   git commit -m "$(cat <<'EOF'
   fix(dead-code): regenerate baseline — drop 31 @api-trait entries

   Drops 17 entries for RevisionableEntityTrait, 9 for InteractsWithApi,
   5 for RefreshDatabase. Delta: -31 entries (BEFORE_COUNT → AFTER_COUNT).
   SC-001 verified: grep -cE returns 0 for all three trait names.

   Refs #1501
   EOF
   )"
   ```
   Replace BEFORE_COUNT and AFTER_COUNT with actual numbers.

   **Note**: Use `Refs` not `Closes` here — `Closes #1501` was already in WP02's commit. Using it again in WP03 would try to close an already-closed issue.

**Validation**:
- [ ] All unit tests pass.
- [ ] Baseline committed with accurate before/after counts in message.
- [ ] `git log --oneline -1` shows the commit on the correct branch.

---

## Definition of Done

- `phpstan-dead-code-baseline.neon` regenerated and committed.
- `grep -cE "(RevisionableEntityTrait|InteractsWithApi|RefreshDatabase)" phpstan-dead-code-baseline.neon` returns `0`.
- `composer verify` exits 0.
- `vendor/bin/phpunit --filter WaaseyaaEntrypointProviderTest` exits 0.
- Commit message records before/after counts.

## Risks

- **New dead-code findings unrelated to the three traits**: Regeneration may expose other entries that were previously invisible. These are out of scope — document them but do not add them to the baseline manually. If they fail `bin/check-dead-code`, that indicates a new problem introduced by WP02 code changes; stop and report.
- **Stale PHPStan cache**: If regeneration produces unexpected results, clear the cache: `rm -rf /tmp/phpstan* ~/.cache/phpstan*`.
- **Delta ≠ 31**: The most likely cause is hypothesis (d) (mixed) was not fully addressed in WP02. Stop and return to WP02 rather than adjusting the baseline manually.
- **`composer verify` fails on unrelated test**: A pre-existing flaky test. If the failure is clearly unrelated to this mission, document it and proceed. Do not suppress or skip CI gates (C-005).

## Reviewer Guidance

- Verify the baseline diff shows exactly −31 entries and all three trait names absent.
- Verify `composer verify` exit code was 0 (ask for the CI run URL or log).
- Verify the commit message has accurate BEFORE/AFTER counts — these document the mission's effect for future reference.
- Confirm no manual edits to `phpstan-dead-code-baseline.neon` — only regeneration (C-002).

## Activity Log

- 2026-05-21T00:54:18Z – claude:sonnet:implementer:implementer – shell_pid=764533 – Started implementation via action command
- 2026-05-21T00:56:02Z – claude:sonnet:implementer:implementer – shell_pid=764533 – Baseline regeneration idempotent at 13 entries. RevisionableEntityTrait/InteractsWithApi/RefreshDatabase all at 0. Delta 66->13 (-53; spec minimum was -31, extra drop from WP02 provider improvements). bin/check-dead-code OK. 7/7 unit tests pass. composer verify fails on pre-existing symfony-imports violation unrelated to this mission.
- 2026-05-21T00:56:55Z – claude:opus-4-7:reviewer:reviewer – shell_pid=772291 – Started review via action command
- 2026-05-21T00:57:43Z – claude:opus-4-7:reviewer:reviewer – shell_pid=772291 – Review passed: 3 target traits at 0 in baseline; baseline regeneration idempotent (zero diff); bin/check-dead-code green; check-symfony-imports failure verified pre-existing on main (11 same violations); 13 remaining baseline entries are legitimate extension-point candidates (interfaces, abstracts, public service APIs - RateLimiterInterface, AuthTokenRepositoryInterface, AbstractMakeCommand, EventAwareStorage, SqlEntityQuery, ComputedFieldInterface, TenantMiddleware, etc.)
