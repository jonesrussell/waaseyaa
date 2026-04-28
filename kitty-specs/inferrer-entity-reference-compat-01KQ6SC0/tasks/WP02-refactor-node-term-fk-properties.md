---
work_package_id: WP02
title: "Refactor Node.uid and Term.parent_id to typed ?int"
dependencies:
- WP01
requirement_refs:
- FR-005
- FR-006
- C-002
- C-003
- NFR-002
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks: []
history: []
authoritative_surface: packages/
execution_mode: code_change
owned_files:
- packages/node/src/Node.php
- packages/taxonomy/src/Term.php
tags: []
assignee: "claude"
agent: "claude"
shell_pid: "16816"
---

# WP02 — Refactor Node.uid and Term.parent_id to typed `?int`

## Goal

Replace the M1 workaround (untyped property + `@var int|null` PHPDoc) on `Node.uid` and `Term.parent_id` with the natural typed declaration `public ?int $... = null;`. This is now safe because WP01 extended `FieldTypeInferrer` to accept `?int → entity_reference`.

## Context

- `packages/node/src/Node.php:52-54`:
  ```php
  /** @var int|null */
  #[Field(type: 'entity_reference', label: 'Author', description: '...', settings: ['weight' => 20, 'target_entity_type_id' => 'user'])]
  public $uid = null;
  ```
- `packages/taxonomy/src/Term.php:29-31`:
  ```php
  /** @var int|null */
  #[Field(type: 'entity_reference', label: 'Parent term', description: '...', settings: ['weight' => 15, 'target_entity_type_id' => 'taxonomy_term'])]
  public $parent_id = null;
  ```

The settings key `target_entity_type_id` is canonical (read by `EntityTypeBuilder`); preserve it. **Do not rename to `target_type`.**

`packages/node` and `packages/taxonomy` are Layer 2 (Content Types); they consume `packages/entity` (Layer 1). No new cross-layer dependencies.

## Acceptance Criteria (from spec)

- **FR-005**: `Node.uid` declared as `public ?int $uid = null;` with the existing `#[Field(type: 'entity_reference', ...)]` attribute and **without** the `@var int|null` PHPDoc.
- **FR-006**: `Term.parent_id` declared as `public ?int $parent_id = null;` with the existing `#[Field(type: 'entity_reference', ...)]` attribute and **without** the `@var int|null` PHPDoc.
- **C-002**: `target_entity_type_id` settings key preserved in both attributes.
- **C-003**: No persisted column shape change; existing `Node` / `Term` tests continue to pass.
- **NFR-002**: `composer phpstan` (level 5) reports no new findings on these files.

## Subtasks

- [ ] T006 — Edit `packages/node/src/Node.php`: drop the `/** @var int|null */` PHPDoc on the `uid` field and change `public $uid = null;` → `public ?int $uid = null;`. Keep the attribute and default unchanged.
- [ ] T007 — Edit `packages/taxonomy/src/Term.php`: drop the `/** @var int|null */` PHPDoc on the `parent_id` field and change `public $parent_id = null;` → `public ?int $parent_id = null;`. Keep the attribute and default unchanged.
- [ ] T008 — Verify downstream getters (`Node::getAuthorId()`, `Term::getParent()/getParentId()` if present) still cast/coerce to `int` correctly with the stricter property type.

## Verification

- `./vendor/bin/phpunit packages/node/tests` green.
- `./vendor/bin/phpunit packages/taxonomy/tests` green.
- `./vendor/bin/phpunit` full suite green (no regressions in API/JSON:API/GraphQL serialization).
- `composer phpstan` no new findings.
- `composer cs-check` clean.
- Manual sanity: `EntityType::fromClass(Node::class)` and `EntityType::fromClass(Term::class)` resolve a `FieldDefinition` for the FK property without throwing.

## Activity Log

- 2026-04-28T13:07:34Z – claude – shell_pid=16816 – Started implementation via action command
- 2026-04-28T13:11:15Z – claude – shell_pid=16816 – Node.uid and Term.parent_id typed as ?int; 144 Node+Term tests green; pre-existing failures unrelated.
- 2026-04-28T13:13:36Z – claude – shell_pid=16816 – Self-review: ?int declarations correct; target_entity_type_id key preserved; 144 Node+Term tests green; pre-existing failures unrelated to my changes.
- 2026-04-28T13:23:52Z – claude – shell_pid=16816 – Mission merged 7090f6d7. | Done override: Mission merged via spec-kitty merge.
