---
work_package_id: WP01
title: Design ratification (#522)
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-005
- NFR-001
- C-001
- C-002
- C-003
- C-004
- C-005
- C-006
- C-007
- C-008
- C-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts only. Ratification edits land directly on main; no implementation branch required.
subtasks:
- T001
- T002
- T003
history:
- date: '2026-04-26'
  note: Initial design draft (commit 1fcdf83d7).
- date: '2026-04-26'
  note: §15 proposed resolutions (commit 705bcf9ce).
- date: '2026-05-02'
  note: §15 resolutions ratified Q1–Q9; mission spec gained Ratified Resolutions block.
authoritative_surface: docs/specs/schema-evolution-v2.md
execution_mode: planning_artifact
mission_id: 01KQN41MQD3Y6PG0PES8XX166F
mission_slug: 529-schema-evolution-v2
owned_files:
- docs/specs/schema-evolution-v2.md
- kitty-specs/529-schema-evolution-v2/spec.md
tags: [design, ratification]
---

# WP01 — Design ratification (#522)

**Status:** done (2026-05-02). Recorded for traceability.

## Objective

Lock the architectural decisions for SchemaDiff v2 so Phase 2+ implementation has a binding contract. The work delivers:

- A 406-line subsystem spec (`docs/specs/schema-evolution-v2.md`) covering: data model (§3), MigrationInterface v2 (§4), compiler contract (§5), ledger extensions (§6), execution model (§7), Composer manifest evolution (§8), coexistence with the legacy engine (§9), non-goals (§10), safety gates (§11), test strategy (§12), and acceptance mapping (§13).
- A locked §15 "Ratified Resolutions (Q1–Q9)" block that turns the §14 open-question list into binding decisions for Phase 2+.
- A mission-spec mirror of the ratification table at the top of `kitty-specs/529-schema-evolution-v2/spec.md`.

## Subtasks

### T001 — Author the SchemaDiff design spec

**Done:** commit `1fcdf83d7`. `docs/specs/schema-evolution-v2.md` §1–§14 covering data model, interface, compiler, ledger, execution, manifest, coexistence, safety, tests, acceptance, open questions.

### T002 — Propose resolutions to §14 open questions

**Done:** commit `705bcf9ce`. §15 added with options + proposed resolution per question, dated 2026-04-26.

### T003 — Ratify §15 resolutions Q1–Q9

**Done:** 2026-05-02. §15 header changed to "Ratified Resolutions (Q1–Q9)"; each Q's "Proposed resolution:" → "Ratified resolution (2026-05-02):". §14 closing note now points at the locked §15. Mission spec (`kitty-specs/529-schema-evolution-v2/spec.md`) gained a top-of-file "Ratified Resolutions" table.

Per the ratification, all WP02–WP11 work proceeds on these binding decisions:

| ID | Decision |
|----|----------|
| Q1 | Retain `migration` as the sole canonical ledger key; add `checksum` + `diff_hash` (nullable). |
| Q2 | SHA-256 over canonical JSON (sorted keys, UTF-8) for both hashes. |
| Q3 | Empty plan = `CompositeDiff([])`; `MigrationPlan` wraps metadata + composite root. |
| Q4 | Single DAG over legacy + v2; tie-break `(package ASC, migration ASC)`. |
| Q5 | Reject `AlterColumn` on SQLite in v1 with stable diagnostic code. |
| Q6 | Reject `AddForeignKey` / `DropForeignKey` on SQLite in v1 (`FOREIGN_KEY_UNSUPPORTED_SQLITE_V1`). |
| Q7 | Atomic types in `waaseyaa/foundation`; entity-scoped factories in `waaseyaa/entity-storage`. |
| Q8 | Extend `bin/waaseyaa migrate` with `--dry-run` and `--verify`; no new command family without ADR. |
| Q9 | No hard removal; array form preferred from Phase 6; string path supported indefinitely. |

## Definition of Done

- [x] Subsystem spec exists at `docs/specs/schema-evolution-v2.md` covering §1–§15.
- [x] §15 marked **RATIFIED — 2026-05-02**.
- [x] Mission spec carries the Q1–Q9 ratification table at the top.
- [x] Document history reflects the ratification entry.

## Risks / Reviewer guidance

- Any future change to a ratified resolution requires an ADR and an explicit overturn note in the subsystem spec — not a silent edit.
- Implementation WPs (WP02–WP11) must reference Q-IDs in their subtask guidance to keep the chain auditable.
