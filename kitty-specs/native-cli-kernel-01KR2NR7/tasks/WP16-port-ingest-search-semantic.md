---
work_package_id: WP16
title: 'Port: Ingest + Search + Semantic'
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
- T071
- T072
- T073
- T074
- T075
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "972920"
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/Command/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/Command/IngestRun*.php
- packages/cli/src/Command/IngestDashboard*.php
- packages/cli/src/Command/SearchReindex*.php
- packages/cli/src/Command/SemanticWarm*.php
- packages/cli/src/Command/SemanticRefresh*.php
- packages/cli/src/Provider/IngestSearchSemanticServiceProvider.php
- packages/cli/tests/Unit/Command/IngestRun*Test.php
- packages/cli/tests/Unit/Command/IngestDashboard*Test.php
- packages/cli/tests/Unit/Command/SearchReindex*Test.php
- packages/cli/tests/Unit/Command/SemanticWarm*Test.php
- packages/cli/tests/Unit/Command/SemanticRefresh*Test.php
- packages/cli/tests/Unit/Command/IngestionFixturePackRegressionTest.php
- packages/cli/tests/Integration/Snapshot/{IngestRun,IngestDashboard,SearchReindex,SemanticWarm,SemanticRefresh}SnapshotTest.php
tags: []
---

# WP16 — Port: Ingest + Search + Semantic

## Branch Strategy

`main` → `main` per lanes.json.

## Subtasks

### T071 — Port `IngestRunCommand` → `IngestRunHandler` + regression test fix

Apply canonical port pattern. The existing `IngestionFixturePackRegressionTest.php` may need updates to use `CliTester`. Run it after the port to confirm green.

### T072 — Port `IngestDashboardCommand` → `IngestDashboardHandler`
### T073 — Port `SearchReindexCommand` → `SearchReindexHandler`
### T074 — Port `SemanticWarmCommand` → `SemanticWarmHandler`
### T075 — Port `SemanticRefreshCommand` → `SemanticRefreshHandler`

Apply canonical pattern.

### T075-bonus — `IngestSearchSemanticServiceProvider`

## Risks

- Ingestion has explicit defaults specs (`docs/specs/ingestion-defaults.md`). The CLI handlers don't define them; just dispatch to `packages/foundation/src/Ingestion/` services. No defaults touched here.

## Definition of Done

- [ ] Five legacy commands deleted, five handlers created.
- [ ] Provider registered.
- [ ] `IngestionFixturePackRegressionTest` green.
- [ ] All snapshot tests pass.
- [ ] Full suite green; gates clean.

## Implementation command

```bash
spec-kitty agent action implement WP16 --agent <name>
```

## Activity Log

- 2026-05-08T14:41:49Z – claude:sonnet:implementer:implementer – shell_pid=968029 – Started implementation via action command
- 2026-05-08T15:03:45Z – claude:sonnet:implementer:implementer – shell_pid=968029 – Ready for review: 5 commands ported (ingest:run, ingest:dashboard, search:reindex, semantic:warm, semantic:refresh), all tests migrated, 4 gates green, 7467 tests pass
- 2026-05-08T15:04:32Z – claude:opus-4-7:reviewer:reviewer – shell_pid=972920 – Started review via action command
