---
work_package_id: WP10
title: 'Port: Optimize group'
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
- T047
- T048
- T049
- T050
agent: "claude:sonnet:implementer:implementer"
shell_pid: "939330"
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/Command/Optimize/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/Command/Optimize/**
- packages/cli/src/Provider/OptimizeServiceProvider.php
- packages/cli/tests/Unit/Command/Optimize/**
- packages/cli/tests/Integration/Snapshot/Optimize*SnapshotTest.php
tags: []
---

# WP10 — Port: Optimize group

## Branch Strategy

`main` → `main` per lanes.json.

## Objective

Port the four Optimize commands using the canonical port pattern (see [WP06](./WP06-port-health-schema.md) §"The canonical port pattern").

## Subtasks

### T047 — Port `OptimizeCommand` → `OptimizeHandler`
### T048 — Port `OptimizeClearCommand` → `OptimizeClearHandler`
### T049 — Port `OptimizeConfigCommand` → `OptimizeConfigHandler`
### T050 — Port `OptimizeManifestCommand` → `OptimizeManifestHandler`

Each handler lives in `packages/cli/src/Command/Optimize/`. Tests follow the same path structure under `tests/Unit/`.

### T050-bonus — `OptimizeServiceProvider`

Yields four `CommandDefinition`s.

## Definition of Done

- [ ] Four legacy command files deleted; four handlers created.
- [ ] `OptimizeServiceProvider` registered.
- [ ] All migrated tests + snapshot tests pass.
- [ ] Full suite green; gates clean.

## Risks

- `optimize:manifest` is referenced by CLAUDE.md ("Run `waaseyaa optimize:manifest`…"). Snapshot test ensures behaviour preserved.

## Reviewer guidance

Same as WP06.

## Implementation command

```bash
spec-kitty agent action implement WP10 --agent <name>
```

## Activity Log

- 2026-05-08T12:46:35Z – claude:sonnet:implementer:implementer – shell_pid=939330 – Started implementation via action command
