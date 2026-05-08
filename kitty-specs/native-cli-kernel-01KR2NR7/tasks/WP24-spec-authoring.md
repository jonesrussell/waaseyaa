---
work_package_id: WP24
title: Spec authoring & cross-refs
dependencies:
- WP23
requirement_refs:
- FR-013
- FR-014
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T107
- T108
- T109
- T110
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "1018593"
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: docs/specs/
execution_mode: planning_artifact
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- docs/specs/cli-kernel.md
- docs/specs/operator-diagnostics.md
- CLAUDE.md
- packages/cli/CLAUDE.md
tags: []
---

# WP24 — Spec authoring & cross-refs

## Branch Strategy

`main` → `main` per lanes.json. **Depends on WP23** (the cut must already have landed; this WP documents what now exists).

## Objective

Author the new spec and update cross-references so the codebase's spec graph reflects reality.

## Subtasks

### T107 — Author `docs/specs/cli-kernel.md`

Length target: 200–400 lines. Sections:

1. **Purpose** — what `packages/cli/` provides and why it exists in the framework.
2. **Layer placement** — Layer 6 (Interfaces).
3. **Public surface** — `CliKernel`, `CliApplication`, `CommandDefinition`, `ArgumentDefinition`, `OptionDefinition`, `CliIO`, `HasNativeCommandsInterface`, `CliTester`.
4. **Argv parser semantics** — the supported subset (verbatim from `kitty-specs/.../research.md` §R-02). Cite explicitly which Symfony Console quirks are unsupported and why.
5. **Exit-code policy** — table of 0/1/2/130 with semantics.
6. **Provider contract** — how providers register commands; example.
7. **Testing** — `CliTester` API and migration mapping from `Symfony\Component\Console\Tester\CommandTester`.
8. **Integration with foundation** — manifest discovery, container resolution.
9. **Out of scope** — shell completion, progress bars, table renderers (deferred).
10. **Related specs** — link to `operator-diagnostics.md`, `infrastructure.md`.

Source much of the content from `kitty-specs/native-cli-kernel-01KR2NR7/spec.md`, `research.md`, and `contracts/`. Keep the spec independent so a reader does not need access to the kitty-specs directory.

### T108 — Update `docs/specs/operator-diagnostics.md`

Replace every reference to `Symfony\Component\Console\…` with `Waaseyaa\Cli\…`. Update the description of how `Health*` and `SchemaCheck*` commands are registered (now via `HealthSchemaServiceProvider` implementing `HasNativeCommandsInterface`). Add a "see also" link to `cli-kernel.md`.

### T109 — Extend orchestration table in `CLAUDE.md`

Open root `CLAUDE.md`. In the Orchestration section table, add a row:

```
| `packages/cli/src/CliKernel.php`, `packages/cli/src/CommandDefinition.php`, `packages/cli/src/Parser/**`, `packages/cli/src/Io/**`, `packages/cli/src/Testing/**`, `bin/waaseyaa` | — | `docs/specs/cli-kernel.md` |
```

If `packages/cli/CLAUDE.md` exists, update it to reference the new spec instead of any Symfony Console wording.

### T110 — Run `tools/drift-detector.sh` and resolve

```bash
tools/drift-detector.sh
```

For every spec the detector flags as stale relative to changes in this mission, update the spec or document why the flag is a false positive (in a comment or commit message). Run again until clean.

## Definition of Done

- [ ] `docs/specs/cli-kernel.md` exists and covers all 10 sections.
- [ ] `docs/specs/operator-diagnostics.md` no longer mentions Symfony Console.
- [ ] `CLAUDE.md` orchestration table includes a row for the CLI kernel.
- [ ] `tools/drift-detector.sh` exits 0.
- [ ] No code changes (this is a docs-only WP).

## Risks

- **Drift detector flags unrelated specs.** Mitigation: investigate each flag; if unrelated, its staleness is pre-existing and out of scope (file a follow-up issue, do not silently fix in this mission).

## Reviewer guidance

- Verify `cli-kernel.md` is internally complete (a reader can understand the kernel without diving into `kitty-specs/`).
- Verify operator-diagnostics has zero `Symfony` mentions.
- Verify CLAUDE.md row syntax matches the existing table.

## Implementation command

```bash
spec-kitty agent action implement WP24 --agent <name>
```

## Activity Log

- 2026-05-08T17:38:48Z – claude:sonnet:implementer:implementer – shell_pid=1017291 – Started implementation via action command
- 2026-05-08T17:42:27Z – claude:sonnet:implementer:implementer – shell_pid=1017291 – Ready for review: cli-kernel.md authored (10 sections), operator-diagnostics.md updated (no Symfony refs), CLAUDE.md orchestration table row added, drift detector clean
- 2026-05-08T17:42:56Z – claude:opus-4-7:reviewer:reviewer – shell_pid=1018593 – Started review via action command
- 2026-05-08T17:44:23Z – claude:opus-4-7:reviewer:reviewer – shell_pid=1018593 – Review passed: cli-kernel.md authored, cross-refs updated
