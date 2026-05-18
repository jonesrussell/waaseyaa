---
work_package_id: WP06
title: CLI commands + scheduler entries
dependencies:
- WP04
requirement_refs:
- FR-005
- FR-006
- FR-007
- FR-030
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-agent-executor-01KRWPK7
base_commit: eb2158425d828b628579169c5ab90ea062a88113
created_at: '2026-05-18T18:19:07.303077+00:00'
subtasks:
- T034
- T035
- T036
- T037
shell_pid: '378305'
history:
- date: '2026-05-18T14:55:10Z'
  actor: tasks-skill
  event: drafted
authoritative_surface: packages/cli/src/Command/Ai/
execution_mode: code_change
owned_files:
- packages/cli/src/Command/Ai/**
- packages/cli/composer.json
- packages/scheduler/src/Schedule/Ai/AgentScheduleEntries.php
- packages/scheduler/composer.json
- tests/Integration/PhaseN/AgentRuntime/CliInlineRunTest.php
- tests/Integration/PhaseN/AgentRuntime/PurgeJobTest.php
tags: []
---

# WP-06 — CLI commands + scheduler entries

## Objective

Ship the three operator-facing `ai:*` CLI commands plus the
scheduler entries that drive the daily purge and the 5-minute
reaper.

## Context

- Spec FRs in scope: **FR-005, FR-006, FR-007, FR-030**.
- NFRs in scope: **NFR-001 (`ai:run --inline` within 10 s), NFR-004 (reaper 5-min tick)**.
- Doctrine spec sections: §"CLI surface", §"Scheduler".
- Spec edge case: `--inline` with `destructive-approval=interactive` SHALL be rejected at parse time.

## Branch strategy

Planning + merge target: `main`. Lane allocated by `spec-kitty agent mission finalize-tasks`.

---

## Subtask T034 — `AiRunCommand` (`ai:run`)

**Purpose:** The operator's primary entry to the agent runtime.

**Steps:**
1. Create `packages/cli/src/Command/Ai/AiRunCommand.php` extending the standard Waaseyaa Symfony Console base.
2. Command name: `ai:run`. Argument: `prompt` (required, string). Options:
   - `--inline` (bool flag) — run synchronously via `AgentRunService::runInline()`. Default: false (async via `enqueue`).
   - `--agent=<id>` (string, default null) — resolve a named `AgentDefinition`. If null, the command falls back to a default ad-hoc bundle using `config.ai.providers[0]`'s default model.
   - `--dry-run` (bool flag) — call each tool's `dryRun()` instead of `execute()`. Propagates onto the run draft.
   - `--watch` (bool flag) — when `--inline` is false, attach an SSE consumer to `/broadcast?channels=agent.run.<id>` and print event names + payloads as they arrive. Default: false.
   - `--destructive-approval=<mode>` (string: `none|all|interactive`, default `none`).
   - `--account=<id>` (int, default service account) — for system / scheduled invocations.
3. Reject the combination `--inline` + `--destructive-approval=interactive` at parse time with a friendly error.
4. For `--inline`: stream `StreamChunk` objects to stdout as the provider produces them (the existing executor surface).
5. For async (default): enqueue, print `run_id`, optionally tail the SSE feed if `--watch`.
6. Use `CommandTester` for unit tests; use the `Waaseyaa\Cli\Testing` helpers for integration coverage.

**Files:**
- `packages/cli/src/Command/Ai/AiRunCommand.php`
- `packages/cli/tests/Unit/Command/Ai/AiRunCommandTest.php`
- `tests/Integration/PhaseN/AgentRuntime/CliInlineRunTest.php` — full end-to-end via `NullLlmProvider`, asserts <10 s wall-clock (**NFR-001**).

**Validation:**
- [ ] `ai:run "ping" --inline` against `NullLlmProvider` completes in under 10 s.
- [ ] `ai:run "x" --inline --destructive-approval=interactive` exits non-zero with a friendly message.
- [ ] `ai:run "x"` enqueues + prints `run_id`.

---

## Subtask T035 — `AiPurgeRunsCommand`  `[P]`

**Purpose:** Implement retention.

**Steps:**
1. Create `packages/cli/src/Command/Ai/AiPurgeRunsCommand.php`. Command name `ai:purge-runs`.
2. Options: `--retention-days=<int>` (default reads `config.ai.run_retention_days`).
3. Algorithm:
   - Compute threshold: `now() - retention_days days`.
   - `AgentRunRepository::findOldByQueuedAt($threshold)` returns rows to delete.
   - Delete the run rows AND the associated `AgentAuditLog` rows (via `AgentAuditLogRepository::purgeOlderThan($threshold)`).
   - Print summary: `Deleted X runs and Y audit rows.`
4. **C-014 invariant:** purge is the only allowed mutation of `AgentAuditLog` outside append.

**Files:**
- `packages/cli/src/Command/Ai/AiPurgeRunsCommand.php`
- `packages/cli/tests/Unit/Command/Ai/AiPurgeRunsCommandTest.php`
- `tests/Integration/PhaseN/AgentRuntime/PurgeJobTest.php`

**Validation:**
- [ ] Rows past retention deleted; rows inside retention preserved.
- [ ] Audit rows deleted in lockstep with their owning runs.

---

## Subtask T036 — `AiReapStalledRunsCommand`  `[P]`

**Purpose:** Wrap the `StalledRunReaper` for CLI / cron invocation.

**Steps:**
1. Create `packages/cli/src/Command/Ai/AiReapStalledRunsCommand.php`. Command name `ai:reap-stalled-runs`.
2. Options: `--max-runtime-seconds=<int>` (default reads `config.ai.max_runtime_seconds`).
3. Algorithm: call `StalledRunReaper::reap($maxRuntimeSeconds)`. Print summary: `Reaped X stalled runs.`
4. Idempotent — calling twice in a row reaps zero on the second call.

**Files:**
- `packages/cli/src/Command/Ai/AiReapStalledRunsCommand.php`
- `packages/cli/tests/Unit/Command/Ai/AiReapStalledRunsCommandTest.php`

**Validation:**
- [ ] Stuck row → reaped; row now `failed/worker_crashed`.
- [ ] Already-terminal row → untouched.

---

## Subtask T037 — Scheduler entries

**Purpose:** Cron / scheduler wiring for the two recurring jobs (**FR-030**).

**Steps:**
1. Create `packages/scheduler/src/Schedule/Ai/AgentScheduleEntries.php`. Use the existing `packages/scheduler` registration mechanism (attribute or service-provider — match what other recurring jobs use; grep `packages/scheduler/src/` for examples).
2. Two entries:
   - **Daily purge:** `ai:purge-runs` at `03:00 UTC` (cron `0 3 * * *`).
   - **5-minute reaper:** `ai:reap-stalled-runs` at every 5 minutes (cron `*/5 * * * *`).
3. Register the class as a service in `packages/scheduler`'s service provider (or add to `extra.waaseyaa.providers` if that's the pattern).
4. Add a unit test that boots the scheduler and asserts both entries are discoverable with the correct cron expressions.

**Files:**
- `packages/scheduler/src/Schedule/Ai/AgentScheduleEntries.php`
- `packages/scheduler/composer.json` — add `waaseyaa/ai-agent` to `require` (the entries reference `AiPurgeRunsCommand` / `AiReapStalledRunsCommand` by name string only, but if FQCN refs are needed the dependency is required).
- `packages/scheduler/tests/Unit/Schedule/Ai/AgentScheduleEntriesTest.php`

**Validation:**
- [ ] Both scheduler entries are discoverable.
- [ ] Cron expressions are correct.
- [ ] `bin/check-package-layers` exits 0.

---

## Definition of Done

- [ ] T034..T037 checkboxes flipped.
- [ ] Three commands ship; `--inline` happy path < 10 s on `NullLlmProvider`.
- [ ] Scheduler entries: daily purge + 5-min reaper.
- [ ] All gates green.

## Risks & mitigations

1. **`--inline` + `interactive` combo silently accepted.** *Mitigation:* parse-time rejection in T034.
2. **Inline bypasses Messenger handler.** *Mitigation:* `runInline()` invokes the handler in-process (WP-04 contract).
3. **Scheduler dependency on ai-agent introduces a circular-ish edge.** *Mitigation:* scheduler depends on cli + ai-agent via published cmd strings only; FQCN references can be replaced with string refs if needed.

## Reviewer guidance

- Run `CliInlineRunTest` and watch the wall-clock — must comfortably fit under 10 s on a developer machine.
- Confirm both scheduler entries actually fire when their cron tick triggers (covered by unit + integration tests).

## Implementation command

```
spec-kitty agent action implement WP06 --agent <name>
```
