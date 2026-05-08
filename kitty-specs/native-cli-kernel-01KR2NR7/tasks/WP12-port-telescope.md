---
work_package_id: WP12
title: 'Port: Telescope group'
dependencies:
- WP05
requirement_refs:
- FR-010
- FR-012
- FR-015
planning_base_branch: main
merge_target_branch: main
branch_strategy: Start `main` → planning base `main` → final merge `main`. Worktree per lanes.json.
subtasks:
- T055
- T056
- T057
- T058
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/Command/Telescope/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/Command/Telescope/**
- packages/cli/src/Provider/TelescopeServiceProvider.php
- packages/cli/tests/Unit/Command/Telescope/**
- packages/cli/tests/Integration/Snapshot/Telescope*SnapshotTest.php
tags: []
---

# WP12 — Port: Telescope group

## Branch Strategy

`main` → `main` per lanes.json.

## Subtasks

### T055 — Port `TelescopeListCommand` → `TelescopeListHandler`
### T056 — Port `TelescopeClearCommand` → `TelescopeClearHandler`
### T057 — Port `TelescopePruneCommand` → `TelescopePruneHandler`
### T058 — Port `TelescopeValidateCommand` → `TelescopeValidateHandler`

Apply canonical port pattern (see WP06).

### T058-bonus — `TelescopeServiceProvider`

Yields four `CommandDefinition`s.

## Risks

- **Logs/telemetry preservation**: per `occurrence_map.yaml`, `logs_telemetry: do_not_change`. Telescope event names, channels, label strings MUST not be modified during the port. Snapshot tests assert this on stdout; logs/metrics emitted internally are also frozen — no rename of any string label.

## Definition of Done

- [ ] Four legacy command files deleted; four handlers created.
- [ ] `TelescopeServiceProvider` registered.
- [ ] All migrated tests + snapshot tests pass.
- [ ] No internal log/metric label changed during port.
- [ ] Full suite green; gates clean.

## Implementation command

```bash
spec-kitty agent action implement WP12 --agent <name>
```
