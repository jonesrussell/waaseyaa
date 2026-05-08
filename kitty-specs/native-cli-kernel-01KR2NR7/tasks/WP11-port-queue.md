---
work_package_id: WP11
title: 'Port: Queue group'
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
- T051
- T052
- T053
- T054
agent: "claude:sonnet:implementer:implementer"
shell_pid: "943315"
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/Command/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/Command/Queue*.php
- packages/cli/src/Provider/QueueServiceProvider.php
- packages/cli/tests/Unit/Command/Queue*Test.php
- packages/cli/tests/Integration/Snapshot/Queue*SnapshotTest.php
tags: []
---

# WP11 — Port: Queue group

## Branch Strategy

`main` → `main` per lanes.json.

## Subtasks

### T051 — Port `QueueWorkCommand` → `QueueWorkHandler`
### T052 — Port `QueueFailedCommand` → `QueueFailedHandler`
### T053 — Port `QueueRetryCommand` → `QueueRetryHandler`
### T054 — Port `QueueFlushCommand` → `QueueFlushHandler`

Apply canonical port pattern (see WP06).

### T054-bonus — `QueueServiceProvider`

Yields four `CommandDefinition`s.

## Risks

- **`Worker::run` baseline-memory** (recent commit b57c00aa1, #1397) lives in `packages/queue/`, not in this WP's scope. The `QueueWorkHandler` just dispatches to `Worker::run`. Don't reach into queue internals.

## Definition of Done

- [ ] Four legacy command files deleted; four handlers created.
- [ ] `QueueServiceProvider` registered.
- [ ] All migrated tests + snapshot tests pass.
- [ ] Full suite green; gates clean.

## Implementation command

```bash
spec-kitty agent action implement WP11 --agent <name>
```

## Activity Log

- 2026-05-08T13:01:54Z – claude:sonnet:implementer:implementer – shell_pid=943315 – Started implementation via action command
