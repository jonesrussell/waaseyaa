---
work_package_id: WP01
title: Pre-cut Baseline & Snapshot Capture
dependencies: []
requirement_refs:
- FR-015
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-native-cli-kernel-01KR2NR7
base_commit: fde0ac64301d39e79c16c06235418716bce0d549
created_at: '2026-05-08T03:07:24.981034+00:00'
subtasks:
- T001
- T002
- T003
- T004
- T005
shell_pid: "875297"
agent: "claude:opus-4-7:reviewer:reviewer"
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: kitty-specs/native-cli-kernel-01KR2NR7/scripts/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- kitty-specs/native-cli-kernel-01KR2NR7/scripts/perf-harness.sh
- kitty-specs/native-cli-kernel-01KR2NR7/scripts/snapshot-capture.sh
- packages/cli/tests/Fixtures/snapshots/**
tags: []
---

# WP01 — Pre-cut Baseline & Snapshot Capture

## Branch Strategy

Planning/base branch: `main`. Final merge target: `main`. Execution worktree is allocated per the lane assigned by `finalize-tasks` (read `lanes.json` to learn the workspace path).

## Objective

Establish the empirical pre-cut baseline that all later WPs measure against:
1. **Performance baseline** for `bin/waaseyaa list` and `bin/waaseyaa health:check --json` (NFR-001/002).
2. **Behavioural snapshot** of every shipped public command's stdout / stderr / exit code (FR-015).

Without this WP nothing later can be verified. WP25 re-runs the same harnesses and asserts the thresholds.

## Context

- Spec: [`spec.md`](../spec.md) §5 FR-015, §6 NFR-001/002, §9 success criteria #2 and #3.
- Plan: [`plan.md`](../plan.md) § Performance Baseline (table currently empty — this WP fills it).
- Research: [`research.md`](../research.md) §R-07 (perf harness contract).
- Snapshots feed `packages/cli/tests/Integration/Snapshot/*Test.php` written in the port WPs (06–22).

## Subtasks

### T001 — Capture pre-cut wall-time + memory baseline

**Purpose**: Record numbers that NFR-001/002 measure relative to.

**Steps**:
1. Run, from repo root, `kitty-specs/native-cli-kernel-01KR2NR7/scripts/perf-harness.sh list 10`.
2. Run `kitty-specs/native-cli-kernel-01KR2NR7/scripts/perf-harness.sh health:check 10`.
3. Record:
   - `wall_median_s` (seconds, 3 decimal places)
   - `mem_max_kb` (kilobytes, peak RSS via `/usr/bin/time -f`)
4. Numbers go into `plan.md` § Performance Baseline (T004).

**Validation**: Two runs are sequential; second run on each command should be within 5% of first (sanity check that median is stable).

### T002 — Commit perf-harness script

**Purpose**: Reproducible measurement.

**Steps**: Create `kitty-specs/native-cli-kernel-01KR2NR7/scripts/perf-harness.sh` with the body in [`research.md`](../research.md) §R-07. Mark executable.

**Files**:
- `kitty-specs/native-cli-kernel-01KR2NR7/scripts/perf-harness.sh` (new, ~30 lines)

**Validation**: `bash -n` passes; `./perf-harness.sh list 1` returns a single-line `wall_median_s=… mem_max_kb=…` style output.

### T003 — Capture stdout/stderr/exit-code snapshots

**Purpose**: Provide ground-truth artefacts the port WPs assert byte-equality against.

**Steps**:
1. Create `kitty-specs/native-cli-kernel-01KR2NR7/scripts/snapshot-capture.sh`. It:
   - Runs `bin/waaseyaa list` and parses the command names (one per line, exclude headings).
   - For each command name, runs:
     - `bin/waaseyaa <name> --help 2>captures/<name>.help.stderr 1>captures/<name>.help.stdout; echo $? > captures/<name>.help.exit`
     - Where the command has a safe no-arg invocation (e.g. `health:check`, `schema:list`), also captures the no-arg run.
   - Sets `WAASEYAA_SNAPSHOT=1` in the environment so any code that emits timestamps emits the fixed value (commands that emit non-deterministic data MUST honour this env var; if a port WP discovers a command that does not, it is a bug to file as a follow-up issue, not a snapshot exception).
2. Move captured files to `packages/cli/tests/Fixtures/snapshots/<name>.help.stdout`, etc.
3. Audit: every command name in `bin/waaseyaa list` has a matching `<name>.help.stdout` fixture.

**Files**:
- `kitty-specs/native-cli-kernel-01KR2NR7/scripts/snapshot-capture.sh` (new, ~80 lines)
- `packages/cli/tests/Fixtures/snapshots/<command>.help.stdout` × N (one per command)
- `packages/cli/tests/Fixtures/snapshots/<command>.help.stderr` × N
- `packages/cli/tests/Fixtures/snapshots/<command>.help.exit` × N

**Validation**: `find packages/cli/tests/Fixtures/snapshots -name '*.help.stdout' | wc -l` equals the number of public command names emitted by `bin/waaseyaa list`.

### T004 — Record numbers in plan.md

**Purpose**: Make the baseline visible and gateable.

**Steps**: Edit `plan.md` § Performance Baseline. Replace `_captured by WP-01_` placeholders with the T001 outputs. Do not invent numbers — paste the literal harness output.

**Files**: `kitty-specs/native-cli-kernel-01KR2NR7/plan.md` (this WP also owns this edit's scope through `authoritative_surface`).

> **Ownership note**: this WP edits plan.md only inside `## Performance Baseline`. The rest of plan.md is owned by /spec-kitty.plan and not modified here.

### T005 — Verify snapshot fixture set is comprehensive

**Purpose**: Catch missing snapshots before they bite WP25.

**Steps**:
1. From the snapshot-capture run, build a sorted list of command names captured.
2. Compare against `bin/waaseyaa list` parsed names.
3. Diff must be empty. Any missing command → re-run capture with that name.

**Files**: a single-shot validation in this WP; nothing committed beyond the verification log in the WP closeout note.

## Definition of Done

- [ ] `kitty-specs/native-cli-kernel-01KR2NR7/scripts/perf-harness.sh` exists, executable, lints with `bash -n`.
- [ ] `kitty-specs/native-cli-kernel-01KR2NR7/scripts/snapshot-capture.sh` exists, executable.
- [ ] `packages/cli/tests/Fixtures/snapshots/` contains one `<cmd>.help.stdout`, `<cmd>.help.stderr`, and `<cmd>.help.exit` per public command.
- [ ] `plan.md` § Performance Baseline filled with literal harness output.
- [ ] `composer test` still green (no source changes; only scripts + fixtures added).

## Risks

- **Non-deterministic command output**: a command emits a timestamp, random string, or absolute path that varies per run. Mitigation: `WAASEYAA_SNAPSHOT=1` env var contract; any command failing to honour it is a bug filed as a follow-up issue, not a snapshot exception.
- **Performance numbers fluctuate**: median of 10 + opcache disabled keeps noise low. If variance > 10% across two consecutive 10-run batches, retry.

## Reviewer guidance

Verify:
- The harness numbers are stable on a re-run (run perf-harness.sh again; median should be within 5%).
- Every command from `bin/waaseyaa list` has a snapshot triple.
- No `git diff` outside `kitty-specs/native-cli-kernel-01KR2NR7/scripts/`, `packages/cli/tests/Fixtures/snapshots/`, and the Performance Baseline section of `plan.md`.

## Implementation command

```bash
spec-kitty agent action implement WP01 --agent <name>
```

## Activity Log

- 2026-05-08T03:07:27Z – claude:opus-4-7:implementer:implementer – shell_pid=868332 – Assigned agent via action command
- 2026-05-08T03:15:36Z – claude:opus-4-7:implementer:implementer – shell_pid=868332 – Ready for review: perf harness + snapshot-capture scripts committed to main (7dde270da); 67-command snapshot fixture set (225 files) committed to worktree branch (a923be435); 7251 tests green; plan.md Performance Baseline filled with literal harness output (list: 0.01s/30080KB, health:check: 0.01s)
- 2026-05-08T03:17:17Z – claude:opus-4-7:reviewer:reviewer – shell_pid=873049 – Started review via action command
- 2026-05-08T03:20:12Z – claude:opus-4-7:reviewer:reviewer – shell_pid=873049 – Moved to planned
- 2026-05-08T03:20:49Z – claude:sonnet:implementer:implementer – shell_pid=873959 – Started implementation via action command
- 2026-05-08T03:23:11Z – claude:sonnet:implementer:implementer – shell_pid=873959 – Cycle 2 fix: plan.md baseline filled with literal harness output (list wall=0.01s mem=30336KB, health:check wall=0.01s)
- 2026-05-08T03:23:33Z – claude:opus-4-7:reviewer:reviewer – shell_pid=875297 – Started review via action command
