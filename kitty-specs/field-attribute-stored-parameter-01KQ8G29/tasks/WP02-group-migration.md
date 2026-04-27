---
work_package_id: WP02
title: Migrate Group to attribute-first; collapse GroupsServiceProvider
dependencies:
- WP01
requirement_refs:
- FR-005
- FR-006
- FR-007
- NFR-001
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks: []
history: []
authoritative_surface: packages/groups/src/
execution_mode: code_change
owned_files:
- packages/groups/src/Group.php
- packages/groups/src/GroupsServiceProvider.php
tags: []
---

# WP02 — Migrate `Group` to attribute-first; collapse `GroupsServiceProvider`

## Goal

Move `Group`'s three universal core fields (`status`, `created_at`, `updated_at`) onto `#[Field(stored: FieldStorage::Data)]` property declarations and replace the `GroupsServiceProvider` workaround with a single `EntityType::fromClass(Group::class)` registration.

## Context

- WP01 has shipped `#[Field(stored:)]`. Use it.
- `packages/groups/src/Group.php` — currently has no `#[Field]` properties (lines 21–55). Match the style of `packages/node/src/Node.php:34-59` and `packages/user/src/User.php:39-48` (public typed properties, no initializers).
- `packages/groups/src/GroupsServiceProvider.php:24-70` — comment block + manual `new EntityType(..., _fieldDefinitions: [...])`. Replace lines 24–70 with `$this->entityType(EntityType::fromClass(Group::class));`.
- `packages/groups/tests/` — current state is 13 passing. Final-state must remain 13/13.

## Acceptance criteria (from spec)

- FR-005: `Group` declares `status`, `created_at`, `updated_at` as public typed `int` properties decorated with `#[Field(stored: FieldStorage::Data)]`.
- FR-006: `GroupsServiceProvider` registers via `$this->entityType(EntityType::fromClass(Group::class));` — workaround block gone.
- FR-007: `./vendor/bin/phpunit packages/groups/tests/` reports 13/13.
- NFR-001: full suite + phpstan + cs-check + layers all green.

## Subtasks

- [ ] T009 — In `Group.php`, add `use Waaseyaa\Entity\Attribute\Field;` and `use Waaseyaa\Field\FieldStorage;` imports.
- [ ] T010 — Add three public typed properties on `Group`:
    ```php
    #[Field(type: 'integer', default: 1, label: 'Status',
        description: 'Whether the group is published.',
        stored: FieldStorage::Data)]
    public int $status;

    #[Field(type: 'integer', label: 'Created at', stored: FieldStorage::Data)]
    public int $created_at;

    #[Field(type: 'integer', label: 'Updated at', stored: FieldStorage::Data)]
    public int $updated_at;
    ```
- [ ] T011 — In `GroupsServiceProvider`, replace the comment block (24–33) and the `new EntityType(...)` block (34–70) with:
    ```php
    $this->entityType(EntityType::fromClass(Group::class));
    ```
- [ ] T012 — Remove now-unused imports in `GroupsServiceProvider` (`FieldDefinition`, `FieldStorage` — confirm by inspection).
- [ ] T013 — `./vendor/bin/phpunit packages/groups/tests/` — confirm 13/13.
- [ ] T014 — `./vendor/bin/phpunit` (full suite), `composer phpstan`, `composer cs-check`, `bin/check-package-layers`, `bin/waaseyaa optimize:manifest` — all green.

## Verification

```bash
./vendor/bin/phpunit packages/groups/tests/        # 13/13
./vendor/bin/phpunit                                # full suite
composer phpstan
composer cs-check
bin/check-package-layers
bin/waaseyaa optimize:manifest
```

## Notes

- Keep `Group`'s constructor signature unchanged; `ContentEntityBase` populates the new typed properties from `$values`.
- Preserve `final class Group extends ContentEntityBase`.
- `EntityType::fromClass()` already infers id, label, description, keys, and field definitions from the `#[ContentEntityType]` / `#[ContentEntityKeys]` / `#[Field]` attributes — no manual `bundleEntityType` or `keys` arrays needed.
- If `EntityType::fromClass()` does not currently honour `bundleEntityType`/`group` from class attributes, fall back to passing those positional kwargs at the call site only — DO NOT regress to `_fieldDefinitions:`.
