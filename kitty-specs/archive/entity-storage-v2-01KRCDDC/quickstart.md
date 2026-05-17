# Quickstart — Entity Storage v2

Three short walkthroughs covering the three roles that interact with the new substrate.

---

## A. Migrator: move one entity type from `sql-blob` to `sql-column`

Goal: take an existing entity type (e.g. `teaching`) from JSON-blob storage to real columns with indexes, with revisions enabled.

```bash
# 1. Generate the per-type migration. Emits a migration file under the owning
#    package and adheres to the ADR-009 manifest format.
bin/waaseyaa make:storage-migration teaching

# 2. Review the generated migration. Confirm:
#    - All declared fields appear with sql-column types per spec §8.2.
#    - The `@expectedReverseSeconds` docblock is set if reverse migration is slow.
#    - The migration includes the `teaching__revision` table because the entity is revisionable.

# 3. Apply in dev/staging first.
bin/waaseyaa migrate

# 4. Update the EntityType definition in code:
#    - Set `primaryStorageBackend: 'sql-column'`.
#    - Confirm `revisionable: true` and `entityKeys['revision'] => 'vid'`.
#    - Add `FieldDefinition::indexed()` to fields you intend to query/sort.

# 5. Run integration tests against the migrated type. The backend-conformance
#    suite (FR-049…FR-052) MUST pass for both sql-blob (legacy data path) and
#    sql-column (new path).

# 6. Roll out to production. WP11 in Minoo proved the pattern with `teaching`;
#    apply the same sequence to subsequent types.
```

If the migration fails halfway:
- The runner halts on the failing step; the migration manifest records the partial state.
- Re-run `bin/waaseyaa migrate` after fixing the cause. Migrations are reversible by default (FR-043).
- Use `bin/waaseyaa migrate:rollback` if reverting is preferred.

---

## B. Backend implementer: register a new field-storage backend

Goal: ship a backend in your package (e.g. `minoo/elasticsearch`) so entity types can route a field to it via `FieldDefinition::storedIn('minoo-elasticsearch')`.

```php
namespace Minoo\Elasticsearch;

use Waaseyaa\EntityStorage\Backend\FieldStorageBackendInterface;
use Waaseyaa\EntityStorage\Backend\HasFieldStorageBackendsInterface;

/**
 * @api
 *
 * Service provider for this package. Discovery is via Composer's `extra.waaseyaa.providers`
 * — no service-locator lookup, no runtime registration calls.
 */
final class ServiceProvider extends \Waaseyaa\Foundation\ServiceProvider implements HasFieldStorageBackendsInterface
{
    public function fieldStorageBackends(): array
    {
        return [new ElasticsearchBackend(...)];
    }
}

/**
 * @api
 *
 * Concrete backend. Must implement every interface method.
 */
final class ElasticsearchBackend implements FieldStorageBackendInterface
{
    public function id(): string { return 'minoo-elasticsearch'; }

    public function read(EntityInterface $entity, FieldDefinition $field): mixed { /* ... */ }
    public function write(EntityInterface $entity, FieldDefinition $field, mixed $value): void { /* ... */ }
    public function delete(EntityInterface $entity): void { /* ... */ }
    public function supportsQuery(FieldDefinition $field, EntityQuery $query): bool { /* ... */ }
}
```

Conformance checklist:

1. The id MUST be stable across releases and MUST NOT collide with reserved ids (`sql-blob`, `sql-column`, `vector`). Convention: `<vendor>-<purpose>`.
2. `write()` MUST be idempotent: writing the same value twice MUST produce the same observable state.
3. `delete()` MUST cascade across all fields this backend holds for the entity.
4. `supportsQuery()` MUST throw `UnsupportedQueryException` at DEFINITION-VALIDATION time, not at query time, when the backend cannot satisfy the declared query/index needs.
5. Extend `Waaseyaa\EntityStorage\Tests\Contract\FieldStorageBackendContractTestCase` (shipped in `waaseyaa/entity-storage` as `autoload-dev` from `testing/`). The contract test suite (WP12) is the conformance gate.

---

## C. Policy author: declare per-revision access

Goal: differentiate "who can view this entity" from "who can view its historical revisions".

```php
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Access\AccessResult;

#[PolicyAttribute(entityType: 'teaching', operations: ['view', 'edit', 'view_revision'])]
final class TeachingAccessPolicy
{
    public function view(Teaching $teaching, AccountInterface $account): AccessResult { /* ... */ }
    public function edit(Teaching $teaching, AccountInterface $account): AccessResult { /* ... */ }

    /**
     * Per-revision access. Called when an account requests a historical revision.
     * If this method is not declared, the framework falls back to `view()`.
     */
    public function viewRevision(
        Teaching $teaching,
        AccountInterface $account,
        Revision $revision,
    ): AccessResult {
        // Example: only knowledge keepers may see draft revisions.
        if ($revision->revisionAuthor === $account->id()) {
            return AccessResult::allowed();
        }
        return $account->hasRole('knowledge_keeper') ? AccessResult::allowed() : AccessResult::forbidden();
    }
}
```

Fallback rule (normative, spec §11.2): policies that do NOT declare `view_revision` fall back to `view`. The framework MUST NOT default-deny. A structured log line on the `entity.lifecycle` channel fires when fallback applies (so observability captures unintentional gaps).

---

## D. Operator: handle a `PartialSaveException`

```php
use Waaseyaa\EntityStorage\Exception\PartialSaveException;

try {
    $coordinator->save($entity, SaveContext::default());
} catch (PartialSaveException $e) {
    $logger->error('Partial save', [
        'entity_type' => $e->entity->getEntityTypeId(),
        'entity_id'   => $e->entity->id(),
        'committed'   => $e->committedBackends,
        'uncommitted' => $e->uncommittedBackends,
        'caused_by'   => $e->causedBy::class,
    ]);

    // Application policy decision:
    // - Run compensating deletes on $e->committedBackends, OR
    // - Surface the entity to a manual reconciliation queue, OR
    // - Retry uncommitted backends if the failure was transient.
    $reconciliationQueue->enqueue($e->entity, $e->uncommittedBackends);
}
```

The framework does NOT attempt cross-backend rollback. That is intentional (spec §3.9, §6.5): true atomicity is unachievable across arbitrary backends. Recovery is per-application.

---

## E. Spec Kitty operator: drive this mission forward

```bash
# From repo root, on the mission branch.
spec-kitty next --agent claude --mission entity-storage-v2-01KRCDDC --result success

# After tasks are materialized, dispatch implementers per spec §13:
spec-kitty agent action implement WP01 --agent sonnet
# (reviewer is opus; escalation target is opus-as-implementer after N=2 rejections — mission.json.)

# When all 12 WPs are merged and acceptance criteria (§14) are met:
spec-kitty accept --mission entity-storage-v2-01KRCDDC
spec-kitty merge --mission entity-storage-v2-01KRCDDC
```

---

## Where to look next

- `kitty-specs/entity-storage-v2-01KRCDDC/spec.md` — full functional requirements.
- `kitty-specs/entity-storage-v2-01KRCDDC/data-model.md` — every stable-surface symbol.
- `kitty-specs/entity-storage-v2-01KRCDDC/contracts/` — normative interface specs per WP.
- `kitty-specs/entity-storage-v2-01KRCDDC/research.md` — decisions, risks, sequencing.
- `docs/specs/entity-storage-v2.md` — canonical doctrine spec (this mission's source of truth).
- `docs/adr/010-multi-backend-field-storage.md`, `011-entity-lifecycle-events.md`, `016-revisions-first-class.md` — governing ADRs.
