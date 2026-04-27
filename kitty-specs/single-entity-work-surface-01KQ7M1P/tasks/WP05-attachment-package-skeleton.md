---
work_package_id: WP05
title: 'F4a: Attachment package skeleton + repository'
dependencies: []
requirement_refs:
- FR-009
- FR-010
- FR-016
- FR-017
- FR-019
- NFR-005
- NFR-006
- NFR-007
- NFR-008
- NFR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Plan from main; lane execution branch is allocated by finalize-tasks via lanes.json. Final merge target is main.
subtasks:
- T019
- T020
- T021
- T022
- T023
- T024
history:
- date: '2026-04-27'
  note: Generated from plan.md + research.md Q5/Q6 + data-model.md § 1.
authoritative_surface: packages/attachment/
execution_mode: code_change
mission_id: 01KQ7M1PHWD8QAQPJC91RAVE0T
mission_slug: single-entity-work-surface-01KQ7M1P
owned_files:
- packages/attachment/composer.json
- packages/attachment/src/Attachment.php
- packages/attachment/src/AttachmentRepository.php
- packages/attachment/src/AttachmentNotFoundException.php
- packages/attachment/src/Schema/AttachmentSchema.php
- packages/attachment/src/AttachmentServiceProvider.php
- packages/attachment/tests/Unit/AttachmentRepositoryTest.php
tags: []
---

# WP05 — F4a: Attachment package skeleton + repository

## Objective

Create the `waaseyaa/attachment` package at Layer 2. Define the `Attachment` content entity, its SQL schema, and the `AttachmentRepository` providing `listFor`, `getActive`, `setActive` (atomic), `save`, `delete`. Wire bindings in `AttachmentServiceProvider`. The access policy ships in WP06; this WP delivers everything else.

## Context (read first)

- **spec.md** FR-009, FR-010, FR-019, NFR-010 — attachment contract.
- **research.md** Q5 (schema split), Q6 (`setActive` atomicity via direct DB transaction).
- **data-model.md § 1** — entity shape, schema columns, indexes, invariants.
- **contracts/README.md** F4 — repository interface.
- **`.claude/rules/entity-storage-invariant.md`** — canonical pipeline. Attachment must follow `EntityRepository` + `SqlStorageDriver` for entity ops; raw DBAL only inside `setActive` for the atomic transaction.
- **CLAUDE.md** "Adding an entity type" checklist — follow it.
- **`packages/node/`** or **`packages/user/`** — reference content-entity packages. Match their structure for `composer.json` autoload, ServiceProvider, schema.

## Branch Strategy

- **Planning base**: `main`
- **Final merge target**: `main`
- Lane via `finalize-tasks`. Use `spec-kitty agent action implement WP05 --agent <name> --mission single-entity-work-surface-01KQ7M1P`.

## Subtasks

### T019 — `composer.json`

**File**: `packages/attachment/composer.json`

```json
{
    "name": "waaseyaa/attachment",
    "description": "Attachment content type with parent-entity reference and at-most-one-active invariant.",
    "type": "library",
    "license": "GPL-2.0-or-later",
    "autoload": {
        "psr-4": { "Waaseyaa\\Attachment\\": "src/" }
    },
    "autoload-dev": {
        "psr-4": { "Waaseyaa\\Attachment\\Tests\\": "tests/" }
    },
    "require": {
        "php": "^8.4",
        "waaseyaa/foundation": "@dev",
        "waaseyaa/entity": "@dev",
        "waaseyaa/entity-storage": "@dev",
        "waaseyaa/access": "@dev",
        "waaseyaa/database-legacy": "@dev"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    },
    "extra": {
        "waaseyaa": {
            "providers": ["Waaseyaa\\Attachment\\AttachmentServiceProvider"]
        }
    },
    "config": {
        "sort-packages": true
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

**Validation**:
- `bin/check-composer-policy` passes.
- `bin/check-package-layers` confirms only L0–L1 deps (foundation L0, entity/entity-storage/access/database-legacy L1).
- Add `packages/attachment` to root `composer.json`'s path repositories and `require` (or update root `composer.json` in WP10's T048 — pick the WP that owns root `composer.json`; here we expect WP10 to handle root).

### T020 — `Attachment` entity class

**File**: `packages/attachment/src/Attachment.php`

```php
<?php
declare(strict_types=1);

namespace Waaseyaa\Attachment;

use Waaseyaa\Entity\ContentEntityBase;

final class Attachment extends ContentEntityBase
{
    public function __construct(array $values = [])
    {
        parent::__construct($values, 'attachment', [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'filename',
        ]);
    }
}
```

Per CLAUDE.md gotcha "Entity subclass constructors", the constructor takes `(array $values)` only and hardcodes entityTypeId + entityKeys. `SqlEntityStorage` uses reflection.

### T021 — `AttachmentSchema`

**File**: `packages/attachment/src/Schema/AttachmentSchema.php`

Per data-model.md § 1 schema table. Use the existing `SqlSchemaHandler` config pattern from `packages/user/src/UserSchema.php` (or wherever a peer entity's schema lives — find via `grep -l "SqlSchemaHandler" packages/*/src/`).

Required columns:
- `id` INTEGER PK AUTOINCREMENT
- `uuid` VARCHAR(36) UNIQUE NOT NULL
- `parent_entity_type` VARCHAR(64) NOT NULL
- `parent_entity_id` VARCHAR(255) NOT NULL
- `is_active` BOOLEAN NOT NULL DEFAULT 0
- `created_at` INTEGER NOT NULL
- `updated_at` INTEGER NOT NULL

`_data` TEXT is added automatically by `SqlSchemaHandler`. It will hold `filename`, `content_type`, `size`, `storage_uri`, `checksum`.

Indexes:
- PK on `id`.
- UNIQUE on `uuid`.
- Composite on `(parent_entity_type, parent_entity_id)`.
- Composite on `(parent_entity_type, parent_entity_id, is_active)` for fast active lookup.

### T022 — `AttachmentRepository` core methods

**File**: `packages/attachment/src/AttachmentRepository.php`

```php
<?php
declare(strict_types=1);

namespace Waaseyaa\Attachment;

use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

final class AttachmentRepository
{
    public function __construct(
        private readonly EntityRepositoryInterface $entityRepository,
        private readonly DatabaseInterface $database,
    ) {}

    /** @return list<Attachment> */
    public function listFor(string $parentEntityType, string $parentId): array
    {
        return $this->entityRepository->findBy(
            ['parent_entity_type' => $parentEntityType, 'parent_entity_id' => $parentId],
            ['id' => 'ASC'],
        );
    }

    public function getActive(string $parentEntityType, string $parentId): ?Attachment
    {
        $results = $this->entityRepository->findBy(
            [
                'parent_entity_type' => $parentEntityType,
                'parent_entity_id' => $parentId,
                'is_active' => true,
            ],
            null,
            1,
        );
        return $results[0] ?? null;
    }

    public function save(Attachment $attachment): void
    {
        $this->entityRepository->save($attachment);
    }

    public function delete(string $attachmentId): void
    {
        $entity = $this->entityRepository->find($attachmentId);
        if ($entity instanceof Attachment) {
            $this->entityRepository->delete($entity);
        }
    }

    // setActive — see T023
}
```

### T023 — `AttachmentRepository::setActive()` with transaction

```php
public function setActive(string $attachmentId): void
{
    $attachment = $this->entityRepository->find($attachmentId);
    if (!$attachment instanceof Attachment) {
        throw new AttachmentNotFoundException($attachmentId);
    }
    $parentType = $attachment->get('parent_entity_type')->value;   // adjust accessor to actual ContentEntityBase API
    $parentId = $attachment->get('parent_entity_id')->value;

    $this->database->transaction(function (DatabaseInterface $db) use ($parentType, $parentId, $attachmentId) {
        // Clear active on all siblings.
        $db->update('attachment')
           ->set('is_active', false)
           ->condition('parent_entity_type', $parentType)
           ->condition('parent_entity_id', $parentId)
           ->execute();
        // Set active on the target.
        $db->update('attachment')
           ->set('is_active', true)
           ->condition('id', $attachmentId)
           ->execute();
    });
}
```

**Notes**:
- The exact `DatabaseInterface::transaction()` signature must match what's in `packages/database-legacy/`. Inspect before coding; adapt as needed.
- This is the **only** place in `AttachmentRepository` that touches DBAL directly. All other methods route through `EntityRepository`. This is the documented exception per `.claude/rules/entity-storage-invariant.md` ("non-entity tables ... or specific atomic operations").
- The siblings being deactivated do not fire entity events — intentional. If consumers need to react to deactivation, a future event subscriber can wrap `setActive` and emit a domain event after commit.
- Add `AttachmentNotFoundException` as a separate class.

**File**: `packages/attachment/src/AttachmentNotFoundException.php`

```php
<?php
declare(strict_types=1);

namespace Waaseyaa\Attachment;

final class AttachmentNotFoundException extends \DomainException
{
    public function __construct(string $attachmentId)
    {
        parent::__construct("Attachment '$attachmentId' not found.");
    }
}
```

### T024 — `AttachmentServiceProvider` + repository unit tests

**File**: `packages/attachment/src/AttachmentServiceProvider.php`

Follow the pattern from `packages/user/src/UserServiceProvider.php` or similar peer L1/L2 packages.

In `register()`:
- Register `AttachmentSchema`.
- Register entity type with `EntityTypeManager` via service provider's standard pattern.
- Register `AttachmentRepository` resolving its `EntityRepositoryInterface` for `attachment` and `DatabaseInterface`.

In `boot()`:
- No-op (the policy from WP06 is auto-discovered via `#[PolicyAttribute]`; no manual registration).

**File**: `packages/attachment/tests/Unit/AttachmentRepositoryTest.php`

**Cases** (use `DBALDatabase::createSqlite()` + real `EntityRepository`):
- `save()` persists; `entityRepository->find($id)` returns the same entity.
- `listFor('node', '1')` returns all attachments with `parent_entity_type='node'`, `parent_entity_id='1'` in id-ascending order.
- `getActive('node', '1')` returns null when no active; returns the unique active when one exists.
- `setActive($id)` flips `is_active=true` on target, `false` on all siblings of the same parent (verify by re-reading and asserting individual flags).
- `setActive` on non-existent id throws `AttachmentNotFoundException`.
- `delete($id)` removes the row; `entityRepository->find($id)` returns null.
- Two parents (`'node:1'`, `'node:2'`): `setActive` on a node:1 attachment does **not** affect node:2 attachments.

(Concurrency test for `setActive` is in WP06 — that's the NFR-010 specific test.)

## Definition of Done

- [ ] `packages/attachment/composer.json` exists, `bin/check-composer-policy` passes, `bin/check-package-layers` passes.
- [ ] `Attachment` extends `ContentEntityBase` with hardcoded entity type/keys.
- [ ] `AttachmentSchema` defines all required columns + indexes.
- [ ] `AttachmentRepository` implements `listFor`, `getActive`, `save`, `delete`, `setActive`.
- [ ] `setActive` uses direct `DatabaseInterface::transaction()` with two UPDATE statements per research.md Q6.
- [ ] `AttachmentNotFoundException` exists.
- [ ] `AttachmentServiceProvider` registers schema, entity type, repository.
- [ ] Repository unit tests cover all happy paths + parent-isolation.
- [ ] `composer phpstan`, `composer cs-check`, PHPUnit pass.
- [ ] No code changes outside `owned_files`.

## Risks

| Risk | Mitigation |
|---|---|
| `DatabaseInterface::transaction()` signature differs from expectation | Read `packages/database-legacy/src/DatabaseInterface.php` first; adapt call. If only `DBALDatabase` exposes transactions, type-hint that concrete class for the `setActive` path (CLAUDE.md gotcha permits this when DBAL `Connection` is needed). |
| Schema columns don't match `findBy` array shape | Test `listFor` and `getActive` against real SQLite. If `findBy` doesn't recognize boolean values for `is_active`, coerce to int (0/1) in the criteria. |
| Entity events fire on the saved attachment but not on deactivated siblings | Intentional and documented. Reviewer should verify and not flag this as inconsistency. |
| `Attachment` entity needs `enforceIsNew()` when constructed with explicit IDs | CLAUDE.md gotcha. Test fixtures that pre-set IDs must call `enforceIsNew()`. Document in test setup. |

## Reviewer guidance

- Verify `setActive` is in a single transaction. A reviewer should run a multi-call interleaving thought experiment: if process A calls `setActive(X)` and process B calls `setActive(Y)` (same parent) at the same time, after commits exactly one row has `is_active=true`. The transaction guarantees this.
- Verify `AttachmentRepository.delete()` does **not** cascade to parent (intentional; documented in data-model.md cross-entity invariants).
- Verify the schema indexes match the data-model.md spec; incorrect indexes will tank `getActive` perf.
- No `@deprecated` symbols anywhere.
- No CHANGELOG edit (WP10).

## Implementation command

```bash
spec-kitty agent action implement WP05 --agent <agent-name> --mission single-entity-work-surface-01KQ7M1P
```

No dependencies — independent of WP01.
