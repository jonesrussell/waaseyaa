---
work_package_id: "WP08"
title: "FR-007 cross-check + FR-010 baseline + NFR-001 benchmark"
dependencies: ["WP03", "WP04", "WP05", "WP06", "WP07"]
planning_base_branch: "main"
merge_target_branch: "main"
branch_strategy: "Planning artifacts were generated on main; completed changes must merge back into main."
subtasks:
  - "T020"
  - "T021"
  - "T022"
phase: "Phase 3 - Verification"
assignee: ""
agent: ""
shell_pid: ""
authoritative_surface: "packages/entity/tests/PhpStan/FieldAttributeRuleTest"
execution_mode: "code_change"
mission_id: "01KQ6XW7Y3QD0JJ7JTP9JCSDPM"
mission_slug: "attribute-first-entity-static-analysis-01KQ6XW7"
owned_files:
  - "packages/entity/tests/PhpStan/FieldAttributeRuleTest.php"
  - "kitty-specs/attribute-first-entity-static-analysis-01KQ6XW7/notes/baseline.md"
  - "kitty-specs/attribute-first-entity-static-analysis-01KQ6XW7/notes/benchmark.md"
tags: []
history:
  - timestamp: "2026-04-27T07:42:00Z"
    agent: "system"
    action: "Prompt generated via /spec-kitty.tasks"
---

# Work Package Prompt: WP08 — Cross-check, baseline, benchmark

## Objective

Mechanically enforce FR-007 (PHPStan errors string-equal runtime errors),
verify FR-010 (no new errors on existing entity-using packages), and verify
NFR-001 (≤ 10% wall-clock regression).

## Subtask Guidance

### T020 — FR-007 cross-check

In `FieldAttributeRuleTest`, add a single integration test
`testRuntimeAndStaticMessagesAgree()`:

For each fixture file under `tests/PhpStan/data/` whose error path also
exists at runtime (FR-002..FR-005):

1. Reflect the property: `(new \ReflectionClass(FQCN))->getProperty(name)`.
2. Construct an equivalent `Field` attribute instance from the AST args.
3. Call `FieldTypeInferrer::infer($prop, $attr)` inside a try/catch.
4. Assert the caught `EntityMetadataException` message string-equals the
   error reported by PHPStan for the same fixture.

For FR-001 (non-public) and FR-006 (non-entity-class), runtime equivalence
isn't directly testable through `infer()`; document the wording origin in
the test docblock and assert against the literal expected string.

### T021 — FR-010 baseline

Run `vendor/bin/phpstan analyse --no-progress` against the entire monorepo
**before** WP02..WP07 land (capture in `kitty-specs/.../notes/baseline.md`
column "before"), and again **after** WP07 (column "after"). Both columns
must show identical error counts on existing entity-using packages
(`packages/genealogy`, `packages/node`, `packages/note`, `packages/taxonomy`,
`packages/user`, `packages/oidc`, `packages/engagement`, `packages/groups`,
`packages/messaging`, `packages/path`).

If "after" introduces errors, those are real misuses to fix in a follow-up
mission — file the follow-up but do **not** suppress them in
`phpstan-baseline.neon` from this mission.

### T022 — NFR-001 benchmark

Capture wall-clock of `vendor/bin/phpstan analyse --no-progress packages/entity/src`,
3 runs each before and after, in `notes/benchmark.md`. Median after ≤
1.10 × median before. If exceeded, profile the rule (PHPStan's
`--debug` output) and optimize before merge.

## Files

- `packages/entity/tests/PhpStan/FieldAttributeRuleTest.php` (cross-check method).
- `kitty-specs/attribute-first-entity-static-analysis-01KQ6XW7/notes/baseline.md` (new).
- `kitty-specs/attribute-first-entity-static-analysis-01KQ6XW7/notes/benchmark.md` (new).

## Validation

- [ ] FR-007 cross-check passes for FR-002..FR-005 fixtures.
- [ ] Baseline note records identical error counts on existing entity-using packages.
- [ ] Benchmark note records ≤ 10% wall-clock regression.
