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
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "942388"
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
- 2026-05-08T12:58:53Z – claude:sonnet:implementer:implementer – shell_pid=939330 – Ready for review: OptimizeHandler, OptimizeClearHandler, OptimizeConfigHandler, OptimizeManifestHandler ported; OptimizeServiceProvider wired; 4 legacy commands deleted; all gates green; 4 snapshot diffs empty
- 2026-05-08T12:59:25Z – claude:opus-4-7:reviewer:reviewer – shell_pid=942388 – Started review via action command
- 2026-05-08T13:01:18Z – claude:opus-4-7:reviewer:reviewer – shell_pid=942388 – Review passed: all 22 ported commands byte-parity green, snapshot tests 22/22, phpstan clean, OptimizeHandler subHandlers properly typed as array<string,Closure(CliIO):int>, CliCommandRegistry::coreCommands() ripple to ConsoleKernel verified at lines 106 and 186, no main-repo contamination from WP10, fixtures additive only.
- 2026-05-08T18:06:24Z – claude:opus-4-7:reviewer:reviewer – shell_pid=942388 – Done override: Mission merged to main (cc36dfcd2)
