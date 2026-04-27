---
work_package_id: WP03
title: FR-010 baseline + NFR-001 benchmark
dependencies:
- WP02
requirement_refs:
- FR-010
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T020
- T021
phase: Phase 3 - Verification
assignee: ''
agent: "claude"
shell_pid: "37360"
history:
- timestamp: '2026-04-27T07:42:00Z'
  agent: system
  action: Prompt generated via /spec-kitty.tasks
authoritative_surface: kitty-specs/attribute-first-entity-static-analysis-01KQ6XW7/notes
execution_mode: code_change
mission_id: 01KQ6XW7Y3QD0JJ7JTP9JCSDPM
mission_slug: attribute-first-entity-static-analysis-01KQ6XW7
owned_files:
- kitty-specs/attribute-first-entity-static-analysis-01KQ6XW7/notes/baseline.md
- kitty-specs/attribute-first-entity-static-analysis-01KQ6XW7/notes/benchmark.md
tags: []
---

# Work Package Prompt: WP03 — FR-010 baseline + NFR-001 benchmark

## Objective

Verify FR-010 (no new errors on existing entity-using packages after the rule
ships) and NFR-001 (≤ 10% wall-clock regression on `vendor/bin/phpstan
analyse packages/entity/src`). FR-007 (string-equality with runtime
exceptions) is verified in-test as part of WP02.

## Subtasks

### T020 — FR-010 baseline note

Run `vendor/bin/phpstan analyse --no-progress` against the entire monorepo
**at the WP02 base commit** (capture as "before") and **after WP02 lands**
(capture as "after"). Compare error counts on:

`packages/genealogy`, `packages/node`, `packages/note`, `packages/taxonomy`,
`packages/user`, `packages/oidc`, `packages/engagement`, `packages/groups`,
`packages/messaging`, `packages/path`.

Record both columns in `kitty-specs/attribute-first-entity-static-analysis-01KQ6XW7/notes/baseline.md`. The "after" column must equal the "before" column on each package; if not, the new errors are real `#[Field]` misuses to fix in a follow-up mission — file the follow-up but do **not** suppress them in `phpstan-baseline.neon` from this mission.

### T021 — NFR-001 benchmark note

Capture wall-clock of `vendor/bin/phpstan analyse --no-progress packages/entity/src`, 3 runs each before WP02 and 3 runs after. Record medians in `kitty-specs/attribute-first-entity-static-analysis-01KQ6XW7/notes/benchmark.md`. Median(after) must be ≤ 1.10 × Median(before). If exceeded, profile the rule (PHPStan `--debug`) and optimize before review.

## Validation

- [ ] `notes/baseline.md` exists and shows identical error counts on the listed packages.
- [ ] `notes/benchmark.md` exists and shows ≤ 10% regression.

## Activity Log

- 2026-04-27T08:13:58Z – claude – shell_pid=37360 – Started implementation via action command
