---
work_package_id: WP05
title: Migrate Production Entity Classes (Identity / Feature Track) + MakeEntityTypeCommand
dependencies:
- WP03
requirement_refs:
- FR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T023
- T024
- T025
- T026
- T027
- T028
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "34728"
history:
- date: '2026-04-27'
  note: Initial generation by /spec-kitty.tasks.
authoritative_surface: packages/oidc/src
execution_mode: code_change
mission_id: 01KQ6DXEQ01S6PVPT6KF5946TA
mission_slug: attribute-first-entity-definition-01KQ6DXE
owned_files:
- packages/oidc/src/Entity/**
- packages/oidc/src/OidcServiceProvider.php
- packages/engagement/src/**
- packages/groups/src/**
- packages/messaging/src/**
- packages/path/src/**
- packages/cli/src/Command/MakeEntityTypeCommand.php
tags: []
---

# WP05 — Migrate Identity Entities + Codegen

## Branch Strategy

- **Planning base**: `main`. **Merge target**: `main`. Worktree per lane.

## Objective

Migrate the identity-and-feature-track production entity classes (oidc, engagement, groups, messaging, path) and update the `make:entity-type` CLI generator to emit attribute-first scaffolds.

## Context

Same migration recipe as WP04. The CLI generator change ensures future-created entities follow the new pattern from day one.

Read these:
- WP04 prompt — recipe is identical.
- `packages/cli/src/Command/MakeEntityTypeCommand.php` — current scaffold output.

---

## Subtask Guidance

### T023 — Migrate `packages/oidc/src/Entity/OidcClient.php` + `OidcServiceProvider.php`

**Steps**: Apply the migration recipe from WP04 to OidcClient. The OidcClient has security-sensitive fields (client_secret, redirect_uris); double-check field types and `readOnly` flags match the existing provider configuration.

**Validation**:
- [ ] `vendor/bin/phpunit packages/oidc/` green.

---

### T024 — Migrate engagement entity + `EngagementServiceProvider.php`

**Steps**:
1. Find the engagement entity class(es). `grep -rln "ContentEntityBase" packages/engagement/src/` to locate.
2. Apply migration recipe.
3. If engagement defines an entity type without a backing class, this is the moment to add the entity class — the new architecture requires one.

**Validation**:
- [ ] `vendor/bin/phpunit packages/engagement/` green.

---

### T025 — Migrate groups entity + `GroupsServiceProvider.php`

**Steps**: Same as T024 for the groups package. The grep `packages/groups/src/GroupsServiceProvider.php` from the audit confirms a `fieldDefinitions:` call site here. Locate the entity class and migrate.

**Validation**:
- [ ] `vendor/bin/phpunit packages/groups/` green.

---

### T026 — Migrate messaging entity + `MessagingServiceProvider.php`

**Steps**: Same as T024 for messaging.

**Validation**:
- [ ] `vendor/bin/phpunit packages/messaging/` green.

---

### T027 — Migrate path entity + `PathServiceProvider.php`

**Steps**: Same as T024 for path.

**Validation**:
- [ ] `vendor/bin/phpunit packages/path/` green.

---

### T028 — Update `packages/cli/src/Command/MakeEntityTypeCommand.php`

**Purpose**: The codegen tool needs to emit attribute-first scaffolds, so newly-generated entities follow the new pattern from day one.

**Steps**:
1. Open `packages/cli/src/Command/MakeEntityTypeCommand.php`. Find the template / generator logic that emits the entity class file.
2. Update the generated entity class template to:
   ```php
   <?php
   declare(strict_types=1);

   namespace {NAMESPACE};

   use Waaseyaa\Entity\Attribute\ContentEntityKeys;
   use Waaseyaa\Entity\Attribute\ContentEntityType;
   use Waaseyaa\Entity\Attribute\Field;
   use Waaseyaa\Entity\ContentEntityBase;

   #[ContentEntityType(id: '{ID}', label: '{LABEL}')]
   #[ContentEntityKeys(label: 'title')]
   final class {CLASS} extends ContentEntityBase
   {
       #[Field] public string $title;
       // Add additional #[Field] properties as needed.
   }
   ```
3. Update the generated `ServiceProvider` snippet (if any) to use `EntityType::fromClass({CLASS}::class)` instead of `new EntityType(...)`.
4. Update any tests for the command (e.g. `packages/cli/tests/Unit/Command/Make/MakeProviderCommandTest.php`).

**Files**:
- `packages/cli/src/Command/MakeEntityTypeCommand.php` (modified).

**Validation**:
- [ ] `vendor/bin/waaseyaa make:entity-type Foo --type=test_foo` (smoke test) generates an entity class with `#[ContentEntityType]` + `#[Field]`.
- [ ] Generated code compiles and round-trips via `EntityType::fromClass()`.
- [ ] CLI tests are green.

**Note**: The companion test `packages/cli/tests/Unit/Command/Make/MakeProviderCommandTest.php` migrates in WP07 as part of test fixture migration — not in this WP. WP05 only updates the production code in `packages/cli/src/`.

---

## Definition of Done

- All 6 subtasks ticked.
- 5 packages have green tests (oidc, engagement, groups, messaging, path).
- CLI generator emits attribute-first scaffold.
- No file outside `owned_files` modified.

## Risks

- **Engagement, groups, messaging, path entity classes may not exist yet**: the audit showed `fieldDefinitions:` in their ServiceProviders, suggesting either entity classes exist outside `Entity/` subdirectories OR the entity types are defined without a backing class. If the latter, this WP must add the entity class as part of the migration.
- **`MakeEntityTypeCommand` template tests** in `packages/cli/tests/`: those tests use `fieldDefinitions:` themselves and migrate in WP07. Coordinate so the command's generated output and its tests don't conflict.

## Reviewer guidance

- Verify each migrated provider uses `EntityType::fromClass()` exclusively.
- Verify the codegen template produces working code (smoke-test by running the generator).
- For OidcClient, double-check the `client_secret` field is properly marked (e.g., `readOnly: true` if appropriate, or with proper settings).

## Implementation command

```
spec-kitty agent action implement WP05 --agent <name>
```

## Activity Log

- 2026-04-27T04:30:30Z – claude:opus-4-7:implementer:implementer – shell_pid=37476 – Started implementation via action command
- 2026-04-27T04:40:39Z – claude:opus-4-7:implementer:implementer – shell_pid=37476 – Ready for review: oidc/engagement/messaging/path migrated to EntityType::fromClass(); groups uses _fieldDefinitions slot due to FieldStorage::Data dependency (Field attribute does not yet expose stored:); MakeEntityTypeCommand emits attribute-first content scaffolds.
- 2026-04-27T04:42:27Z – claude:opus-4-7:reviewer:reviewer – shell_pid=23536 – Started review via action command
- 2026-04-27T04:45:48Z – claude:opus-4-7:reviewer:reviewer – shell_pid=23536 – Moved to planned
- 2026-04-27T04:46:39Z – claude:opus-4-7:implementer:implementer – shell_pid=33552 – Started implementation via action command
- 2026-04-27T04:50:29Z – claude:opus-4-7:implementer:implementer – shell_pid=33552 – Cycle 2: CLI test assertions updated for attribute-first template
- 2026-04-27T04:50:58Z – claude:opus-4-7:reviewer:reviewer – shell_pid=34728 – Started review via action command
- 2026-04-27T04:51:51Z – claude:opus-4-7:reviewer:reviewer – shell_pid=34728 – Cycle 2: review passed - CLI test assertions updated
