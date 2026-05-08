---
work_package_id: WP18
title: 'Port: Bundle + Fixture scaffolds'
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
- T081
- T082
- T083
- T084
agent: "claude:sonnet:implementer:implementer"
shell_pid: "988812"
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/Command/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/Command/BundleScaffold*.php
- packages/cli/src/Command/FixtureScaffold*.php
- packages/cli/src/Command/FixtureGenerate*.php
- packages/cli/src/Command/FixturePackRefresh*.php
- packages/cli/src/Provider/BundleFixtureServiceProvider.php
- packages/cli/tests/Unit/Command/BundleScaffold*Test.php
- packages/cli/tests/Unit/Command/FixtureScaffold*Test.php
- packages/cli/tests/Unit/Command/FixtureGenerate*Test.php
- packages/cli/tests/Unit/Command/FixturePackRefresh*Test.php
- packages/cli/tests/Integration/Snapshot/{BundleScaffold,FixtureScaffold,FixtureGenerate,FixturePackRefresh}SnapshotTest.php
tags: []
---

# WP18 — Port: Bundle + Fixture scaffolds

## Branch Strategy

`main` → `main` per lanes.json.

## Subtasks

### T081 — Port `BundleScaffoldCommand` → `BundleScaffoldHandler`
### T082 — Port `FixtureScaffoldCommand` → `FixtureScaffoldHandler`
### T083 — Port `FixtureGenerateCommand` → `FixtureGenerateHandler`
### T084 — Port `FixturePackRefreshCommand` → `FixturePackRefreshHandler`

Apply canonical port pattern (see WP06).

### T084-bonus — `BundleFixtureServiceProvider`

## Definition of Done

- [ ] Four legacy commands deleted, four handlers created.
- [ ] Provider registered.
- [ ] Tests + snapshot tests pass.
- [ ] Full suite green.

## Implementation command

```bash
spec-kitty agent action implement WP18 --agent <name>
```

## Activity Log

- 2026-05-08T15:43:14Z – claude:sonnet:implementer:implementer – shell_pid=985272 – Started implementation via action command
- 2026-05-08T15:54:03Z – claude:sonnet:implementer:implementer – shell_pid=985272 – Ready for review: ported BundleScaffoldHandler, FixtureScaffoldHandler, FixtureGenerateHandler, FixturePackRefreshHandler; BundleFixtureServiceProvider registered; ghost refs cleaned from CliCommandRegistry and IngestionFixturePackRegressionTest; 4 WP18 snapshots generated; BundleFixtureSnapshotTest green; full suite 7488/7488; phpstan [OK] No errors
- 2026-05-08T15:54:35Z – claude:opus-4-7:reviewer:reviewer – shell_pid=988303 – Started review via action command
- 2026-05-08T15:55:57Z – claude:opus-4-7:reviewer:reviewer – shell_pid=988303 – Moved to planned
- 2026-05-08T15:56:24Z – claude:sonnet:implementer:implementer – shell_pid=988812 – Started implementation via action command
- 2026-05-08T15:58:53Z – claude:sonnet:implementer:implementer – shell_pid=988812 – Cycle 2 fix: __ naming, 3 WP01 baselines preserved, 1 pseudo for scaffold:bundle
