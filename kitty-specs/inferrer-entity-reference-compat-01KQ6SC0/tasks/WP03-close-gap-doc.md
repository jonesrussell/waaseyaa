---
work_package_id: WP03
title: "Close transitional gap #3 in entity-system spec"
dependencies:
- WP02
requirement_refs:
- FR-007
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks: []
history: []
authoritative_surface: docs/specs/
execution_mode: code_change
owned_files:
- docs/specs/entity-system.md
tags: []
assignee: "claude"
agent: "claude"
shell_pid: "12160"
---

# WP03 — Close transitional gap #3 in entity-system spec

## Goal

Update `docs/specs/entity-system.md` §"Known Transitional Gaps" item 3 to mark the `entity_reference`-on-scalar bullet **closed** by this mission, matching the just-closed `stored:` bullet style on line 589.

## Context

- Current text (`docs/specs/entity-system.md:587-599`):
  > **`entity_reference` is rejected on scalar PHP types by `FieldTypeInferrer`.** The inferrer doesn't currently have a compatibility group for `?int` or `?string` → `entity_reference`. Properties like `Node.uid`, `Term.parent_id`, etc. work around this with untyped properties + `@var` PHPDoc … A future `inferrer-entity-reference-compat` mission will extend `FieldTypeInferrer` …
- Style precedent on line 589:
  > **~~No `stored:` parameter on `#[Field]`.~~** **Closed in mission `field-attribute-stored-parameter-01KQ8G29`.** … The `groups/Group` entity is migrated to attribute-first declaration …

## Acceptance Criteria (from spec)

- **FR-007**: bullet text updated to use the same closed-bullet style: strike-through the original problem statement + "Closed in mission `inferrer-entity-reference-compat-01KQ6SC0`." + a brief one-line description of the resolution and the typed example.

## Subtasks

- [ ] T009 — Replace lines 591-599 in `docs/specs/entity-system.md` with a closed-bullet entry naming this mission slug, citing the new asymmetric rule, and showing the typed `public ?int $uid;` declaration.

## Verification

- `bash tools/drift-detector.sh` (or `tools/drift-detector.sh` per platform) reports no drift in `entity-system.md`.
- Visual inspection: bullet matches the style on line 589.
- No code change in this WP; CI/test-suite results from WP01/WP02 still hold.

## Activity Log

- 2026-04-28T13:11:39Z – claude – shell_pid=12160 – Started implementation via action command
- 2026-04-28T13:12:23Z – claude – shell_pid=12160 – Spec doc gap #3 closed; matches stored: bullet style.
- 2026-04-28T13:13:51Z – claude – shell_pid=12160 – Self-review: spec doc bullet matches stored: closed-bullet style; uses canonical target_entity_type_id key in example; references mission slug.
- 2026-04-28T13:23:55Z – claude – shell_pid=12160 – Mission merged 7090f6d7. | Done override: Mission merged via spec-kitty merge.
