---
work_package_id: WP21
title: 'Port: Misc cluster B (Install/Route/Serve/Sync/Version) + Provenance'
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
- T094
- T095
- T096
- T097
- T098
- T099
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "1003727"
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/Command/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/Command/Install*.php
- packages/cli/src/Command/RouteList*.php
- packages/cli/src/Command/Serve*.php
- packages/cli/src/Command/SyncRules*.php
- packages/cli/src/Command/WaaseyaaVersion*.php
- packages/cli/src/Provenance/ComposerProvenanceReporter.php
- packages/cli/src/Provider/MiscBServiceProvider.php
- packages/cli/tests/Unit/Command/Install*Test.php
- packages/cli/tests/Unit/Command/RouteList*Test.php
- packages/cli/tests/Unit/Command/Serve*Test.php
- packages/cli/tests/Unit/Command/SyncRules*Test.php
- packages/cli/tests/Command/SyncRulesCommandTest.php
- packages/cli/tests/Unit/Command/WaaseyaaVersion*Test.php
- packages/cli/tests/Integration/Snapshot/{Install,RouteList,Serve,SyncRules,WaaseyaaVersion}SnapshotTest.php
tags: []
---

# WP21 — Port: Misc cluster B + Provenance

## Branch Strategy

`main` → `main` per lanes.json.

## Subtasks

### T094 — Port `InstallCommand` → `InstallHandler`
### T095 — Port `RouteListCommand` → `RouteListHandler`
### T096 — Port `ServeCommand` → `ServeHandler`
### T097 — Port `SyncRulesCommand` → `SyncRulesHandler`
### T098 — Port `WaaseyaaVersionCommand` → `WaaseyaaVersionHandler`

Apply canonical port pattern (see WP06). Note `SyncRulesCommandTest.php` lives at `tests/Command/SyncRulesCommandTest.php` (no Unit/ prefix); ownership glob covers it.

### T099 — Refactor `ComposerProvenanceReporter`

This class is in `packages/cli/src/Provenance/`. It currently imports `Symfony\Component\Console\Output\OutputInterface` to format reports. Refactor:
- Replace `OutputInterface` parameters with `CliOutput` (or `CliIO`).
- Keep all reporting logic and formatting intact.
- Migrate any test using Symfony output buffering to `BufferedCliOutput`.

### T099-bonus — `MiscBServiceProvider`

Yields five `CommandDefinition`s (`InstallHandler`, `RouteListHandler`, `ServeHandler`, `SyncRulesHandler`, `WaaseyaaVersionHandler`).

## Risks

- `serve` runs the PHP built-in server. Preserve the existing port + host defaults.
- `--version` is a kernel-level flag (auto-injected by `CliKernel`). The `WaaseyaaVersionHandler` for the `version` command is distinct from `--version` — the command emits richer info; preserve that distinction.

## Definition of Done

- [ ] Five legacy commands deleted, five handlers created.
- [ ] `ComposerProvenanceReporter` no longer imports `Symfony\Component\Console`.
- [ ] `MiscBServiceProvider` registered.
- [ ] Tests + snapshot tests pass.
- [ ] Full suite green.

## Implementation command

```bash
spec-kitty agent action implement WP21 --agent <name>
```

## Activity Log

- 2026-05-08T16:31:49Z – claude:sonnet:implementer:implementer – shell_pid=998783 – Started implementation via action command
- 2026-05-08T16:51:54Z – claude:sonnet:implementer:implementer – shell_pid=998783 – Ready for review: 5 handlers (Install/RouteList/Serve/SyncRules/WaaseyaaVersion), MiscBServiceProvider, ComposerProvenanceReporter native path. All 4 gates green. 7508/0/0 phpunit. 5 snapshot tests green byte-for-byte.
- 2026-05-08T16:52:30Z – claude:opus-4-7:reviewer:reviewer – shell_pid=1003727 – Started review via action command
