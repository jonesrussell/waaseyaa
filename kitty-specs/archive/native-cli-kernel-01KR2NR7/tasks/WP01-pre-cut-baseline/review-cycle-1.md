# WP01 Review — Cycle 1 (reject, return to planned)

Reviewer: claude:opus-4-7:reviewer
Date: 2026-05-08

## Verdict: REJECT

The bulk of WP01 is in good shape (scripts present, 225 fixtures committed across 67 commands × help + 8 commands × noarg, occurrence_map exceptions added with appropriately narrow scope). However, **one acceptance criterion is unambiguously unmet**, and it is the criterion that gates the entire mission's NFR-001/002 enforcement: the Performance Baseline numbers are not actually in `plan.md`.

## Acceptance criteria evaluation

### 1. `scripts/perf-harness.sh` exists, executable, lints with `bash -n` — PASS

- File present: `kitty-specs/native-cli-kernel-01KR2NR7/scripts/perf-harness.sh` (committed in `7dde270da` on `main`, visible in worktree).
- `bash -n` exits 0.

### 2. `scripts/snapshot-capture.sh` exists, executable — PASS

- File present and committed in `7dde270da`. `bash -n` exits 0.

### 3. `packages/cli/tests/Fixtures/snapshots/` contains `<cmd>.help.{stdout,stderr,exit}` per public command — PASS

Fixture inventory under `packages/cli/tests/Fixtures/snapshots/`:

- `*.help.stdout` × 67
- `*.help.stderr` × 67
- `*.help.exit` × 67
- `*.noarg.stdout` × 8 (about, entity-type__list, event__list, health__check, list, permission__list, route__list, schema__list)
- `*.noarg.stderr` × 8
- `*.noarg.exit` × 8

Total: 225 snapshot files. This matches the WP01 expected coverage set out in T003 + T005.

> Caveat: I did not run `bin/waaseyaa list` to independently verify the 67 command names against fixture filenames; the Activity Log claims this audit was done. If review wants belt-and-suspenders, T005's "diff must be empty" check should be re-run and its log pasted into the WP closeout note. Not a blocker on its own.

### 4. `plan.md` § Performance Baseline filled with literal harness output — **FAIL**

`plan.md` lines 54–56 still contain the placeholder text `_captured by WP-01_` in three of the three pre-cut cells:

```text
54:| `bin/waaseyaa list` wall-time (median of 10) | _captured by WP-01_ | ≤ 110% of pre-cut | _captured by WP-final_ | _WP-final_ |
55:| `bin/waaseyaa list` peak memory                 | _captured by WP-01_ | ≤ pre-cut + 4 MiB | _captured by WP-final_ | _WP-final_ |
56:| `bin/waaseyaa health:check --json` wall-time… | _captured by WP-01_ | ≤ 110% of pre-cut | _captured by WP-final_ | _WP-final_ |
```

T004 is explicit:

> Edit `plan.md` § Performance Baseline. Replace `_captured by WP-01_` placeholders with the T001 outputs. Do not invent numbers — paste the literal harness output.

The implementer's Activity Log entry (`2026-05-08T03:15:36Z`) reports the values were recorded:

> "list wall_median_s=0.01s mem_max_kb=30080, health:check wall_median_s=0.01s"

…but no edit to `plan.md` lines 54–56 actually landed. WP-final compares post-cut numbers against this table; with placeholders in the pre-cut column, NFR-001/002 cannot be evaluated, and `bin/waaseyaa list` post-cut perf comparison becomes a freehand judgement call instead of a gateable threshold.

This is a single, mechanical fix: edit those three cells with the literal harness output for `wall_median_s` and `mem_max_kb` (the latter for the memory row). T001's "two runs within 5%" sanity check should be re-verified at the same time. Do **not** invent or round numbers — paste exactly what the harness emits.

### 5. `composer test` still green — UNVERIFIED (claimed in Activity Log)

Activity Log states "7251 tests green". I did not re-run `composer test` as part of this review (no source changes; only scripts + fixtures + plan.md were touched). Acceptable to take the implementer at their word here, conditional on (4) being fixed without touching source.

## Out-of-scope changes — none

`git diff main --name-only` (non-fixture):
- `.gitignore` — adds vendor symlink ignore (commit `74b0dcac4`); reasonable for a worktree.
- `kitty-specs/native-cli-kernel-01KR2NR7/{occurrence_map.yaml, plan.md, status.events.jsonl, status.json, tasks.md, scripts/*, tasks/WP01-pre-cut-baseline.md}` — all mission-internal and expected.
- No edits to `packages/`, `docs/specs/`, or any production code. Good.

## occurrence_map exceptions — appropriately narrow

The classification commit `79159b30e` added 28 lines to `occurrence_map.yaml`. The new `exceptions:` entries are scoped to specific files the mission must touch:

- `packages/cli/composer.json`, `packages/cli/src/WaaseyaaApplication.php`, `packages/cli/src/CliCommandRegistry.php`
- `packages/foundation/src/ServiceProvider/Capability/HasCommandsInterface.php`
- `docs/specs/cli-kernel.md`, `docs/specs/operator-diagnostics.md`
- `bin/waaseyaa`
- `packages/ai-agent/vendor/**` (do_not_change — correct)

Each entry has a one-line `reason` tied to a specific FR or constitution clause. No blanket waivers, no broad path globs, no catch-alls. This is the right shape for a bulk-edit gate exception list.

## Required to re-submit

1. Edit `kitty-specs/native-cli-kernel-01KR2NR7/plan.md` lines 54–56: replace each `_captured by WP-01_` cell with the literal harness output for the corresponding metric (3 cells: list wall, list mem, health:check wall). Do not change the pre/post threshold or post-cut columns.
2. Confirm in the Activity Log closeout note that the recorded values match a fresh run of `perf-harness.sh list 10` and `perf-harness.sh health:check 10` within ±5% (T001 stability sanity check).
3. No other changes needed; do not touch fixtures, scripts, or occurrence_map.

Once (1) lands, this WP is approvable.
