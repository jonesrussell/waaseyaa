---
work_package_id: WP13
title: 'Port: Schedule + Perf'
dependencies:
- WP05
requirement_refs:
- FR-010
- FR-012
- FR-015
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T059
- T060
- T061
- T062
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "953818"
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/Command/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/Command/Schedule*.php
- packages/cli/src/Command/Perf/**
- packages/cli/src/Provider/SchedulePerfServiceProvider.php
- packages/cli/tests/Unit/Command/Schedule*Test.php
- packages/cli/tests/Unit/Command/Perf/**
- packages/cli/tests/Integration/Snapshot/{Schedule,Perf}*SnapshotTest.php
tags: []
---

# WP13 — Port: Schedule + Perf

## Branch Strategy

`main` → `main` per lanes.json.

## Subtasks

### T059 — Port `ScheduleListCommand` → `ScheduleListHandler`
### T060 — Port `ScheduleRunCommand` → `ScheduleRunHandler`
### T061 — Port `Perf/PerformanceBaselineCommand` → `PerformanceBaselineHandler`
### T062 — Port `Perf/PerformanceCompareCommand` → `PerformanceCompareHandler`

Apply canonical port pattern (see WP06).

### T062-bonus — `SchedulePerfServiceProvider`

Yields four `CommandDefinition`s.

## Definition of Done

- [ ] Four legacy command files deleted; four handlers created.
- [ ] `SchedulePerfServiceProvider` registered.
- [ ] All migrated tests + snapshot tests pass.
- [ ] Full suite green; gates clean.

## Risks

- `perf:baseline` and `perf:compare` produce numerical output. Use `WAASEYAA_SNAPSHOT=1` for the snapshot fixtures (set by WP01 capture script); the handlers must honour the env var to emit deterministic output.

## Implementation command

```bash
spec-kitty agent action implement WP13 --agent <name>
```

## Activity Log

- 2026-05-08T13:47:43Z – claude:sonnet:implementer:implementer – shell_pid=950112 – Started implementation via action command
- 2026-05-08T13:59:28Z – claude:sonnet:implementer:implementer – shell_pid=950112 – Ready for review: ScheduleListHandler, ScheduleRunHandler, PerformanceBaselineHandler, PerformanceCompareHandler ported via SchedulePerfServiceProvider. All gates GREEN (cs-check/phpstan/phpunit 7466/7466). Per-command diff EMPTY for all 4 commands. schedule fixtures pseudo-baseline; perf fixtures match WP01 byte-for-byte.
- 2026-05-08T13:59:50Z – claude:opus-4-7:reviewer:reviewer – shell_pid=953818 – Started review via action command
