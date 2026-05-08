---
work_package_id: WP19
title: 'Port: Other scaffolds (Relationship/Workflow/Extension/Auth)'
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
- T085
- T086
- T087
- T088
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/Command/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/Command/RelationshipTypeScaffold*.php
- packages/cli/src/Command/WorkflowScaffold*.php
- packages/cli/src/Command/ExtensionScaffold*.php
- packages/cli/src/Command/ScaffoldAuth*.php
- packages/cli/src/Provider/OtherScaffoldsServiceProvider.php
- packages/cli/tests/Unit/Command/RelationshipTypeScaffold*Test.php
- packages/cli/tests/Unit/Command/WorkflowScaffold*Test.php
- packages/cli/tests/Unit/Command/ExtensionScaffold*Test.php
- packages/cli/tests/Unit/ScaffoldAuthCommandTest.php
- packages/cli/tests/Unit/Command/ScaffoldAuth*Test.php
- packages/cli/tests/Integration/Snapshot/{RelationshipTypeScaffold,WorkflowScaffold,ExtensionScaffold,ScaffoldAuth}SnapshotTest.php
tags: []
---

# WP19 — Port: Other scaffolds

## Branch Strategy

`main` → `main` per lanes.json.

## Subtasks

### T085 — Port `RelationshipTypeScaffoldCommand` → `RelationshipTypeScaffoldHandler`
### T086 — Port `WorkflowScaffoldCommand` → `WorkflowScaffoldHandler`
### T087 — Port `ExtensionScaffoldCommand` → `ExtensionScaffoldHandler`
### T088 — Port `ScaffoldAuthCommand` → `ScaffoldAuthHandler`

Apply canonical port pattern (see WP06). Note `ScaffoldAuthCommandTest.php` lives at `tests/Unit/ScaffoldAuthCommandTest.php` (top-level), not under `tests/Unit/Command/` — the ownership glob covers both.

### T088-bonus — `OtherScaffoldsServiceProvider`

## Definition of Done

- [ ] Four legacy commands deleted, four handlers created.
- [ ] Provider registered.
- [ ] Tests + snapshot tests pass.
- [ ] Full suite green.

## Implementation command

```bash
spec-kitty agent action implement WP19 --agent <name>
```
