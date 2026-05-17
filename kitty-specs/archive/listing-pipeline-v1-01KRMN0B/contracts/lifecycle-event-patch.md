# Contract: AfterSaveEvent / AfterDeleteEvent affectedLangcodes patch

**Stability scope:** Charter §5.3 (entity lifecycle events — existing surface; this is an additive amendment)
**FRs covered:** FR-038..FR-041, §11.7 decision (R-07)
**Owned by:** WP08

## Scope

Minimal additive patch to `packages/foundation/src/Event/AfterSaveEvent.php` and `AfterDeleteEvent.php`. Adds one optional `readonly ?array $affectedLangcodes` property to each event. Backfilled by `packages/entity-storage/src/SqlStorageDriver.php` (M-006-shipped translatable write path). Consumed by `Waaseyaa\Listing\ListingCacheInvalidator` for per-langcode tag emission.

## AfterSaveEvent (BEFORE)

```php
namespace Waaseyaa\Foundation\Event;

final readonly class AfterSaveEvent extends EntityEvent
{
    public function __construct(
        public EntityInterface $entity,
        public ?EntityInterface $originalEntity = null,
    ) { parent::__construct(/* … */); }
}
```

## AfterSaveEvent (AFTER)

```php
namespace Waaseyaa\Foundation\Event;

final readonly class AfterSaveEvent extends EntityEvent
{
    /**
     * @param ?list<non-empty-string> $affectedLangcodes  null = inferred via $entity->activeLangcode() at consumption time
     */
    public function __construct(
        public EntityInterface $entity,
        public ?EntityInterface $originalEntity = null,
        public ?array $affectedLangcodes = null,
    ) { parent::__construct(/* … */); }
}
```

## AfterDeleteEvent (BEFORE / AFTER)

Mirror patch: third optional `?array $affectedLangcodes = null` property added.

## Backwards compatibility

- Existing callers passing positional `(entity, originalEntity)` arguments compile unchanged.
- Existing callers using named `entity: ..., originalEntity: ...` compile unchanged.
- All existing event listeners (which read `$event->entity` etc.) see no change.

## Backfill responsibility

| Caller | Behaviour | Reasoning |
|---|---|---|
| `Waaseyaa\EntityStorage\SqlStorageDriver::save()` on a translatable entity | Pass `affectedLangcodes: $writtenLangcodes` | Driver knows exactly which langcodes were written in this save (it just executed the INSERT/UPDATE statements) |
| `Waaseyaa\EntityStorage\SqlStorageDriver::save()` on a non-translatable entity | Leave `affectedLangcodes` default `null` | No translation rows; the active langcode tag is implicit |
| `Waaseyaa\EntityStorage\InMemoryEntityStorage::save()` on translatable | Pass `affectedLangcodes` if storage tracked the langcodes | Test backend; honour the contract when ergonomic |
| Hand-emitted events from app code | `null` is the safe default | App can opt in by passing the langcode list when it knows |

## Consumer behaviour (`ListingCacheInvalidator`)

```php
public function onAfterSave(AfterSaveEvent $event): void
{
    $entity = $event->entity;
    $tags = ["entity:{$entity->getEntityTypeId()}", "entity:{$entity->getEntityTypeId()}:{$entity->id()}"];

    if ($entity instanceof TranslatableInterface) {
        $langcodes = $event->affectedLangcodes ?? [$entity->activeLangcode()];
        foreach ($langcodes as $lc) {
            $tags[] = "entity:{$entity->getEntityTypeId()}:{$entity->id()}:{$lc}";
        }
    }

    foreach ($tags as $tag) {
        try {
            $this->cache->invalidateByTag($tag);
        } catch (\Throwable $t) {
            $this->logger->warning('cache invalidation failed', ['tag' => $tag, 'exception' => $t]);
        }
    }
}
```

`onAfterDelete` is structurally identical, reading `$event->affectedLangcodes` (typically all langcodes that were translated on the deleted entity — `SqlStorageDriver` populates from the translation-table rows it just deleted).

## Stability commitment

- The new `$affectedLangcodes` property is stable from the next release that ships this mission. Future event-surface additions must be additive (new optional properties, defaults compatible with existing callers).
- The fallback semantics (`null` → `[$entity->activeLangcode()]` in the consumer) are stable. Even if future M-N work adds a richer langcode-affecting-event-surface, this fallback continues to work for old non-backfilling callers.

## Test surface

In `packages/foundation/tests/Unit/Event/`:
- `afterSaveEventAcceptsAffectedLangcodes`
- `afterSaveEventDefaultsAffectedLangcodesNull`
- `afterDeleteEventAcceptsAffectedLangcodes`
- `existingPositionalConstructionStillCompiles` (BC test — important)

In `packages/listing/tests/Backend/`:
- `cacheInvalidatorEmitsTagPerAffectedLangcode` (uses translatable fixture)
- `cacheInvalidatorFallsBackToActiveLangcodeWhenNull` (uses non-backfilling event)
- `cacheInvalidatorContinuesOnCacheBackendError` (FR-040 best-effort behaviour)

In `packages/entity-storage/tests/Backend/`:
- `sqlStorageDriverBackfillsAffectedLangcodesOnTranslatableSave`
- `sqlStorageDriverLeavesAffectedLangcodesNullOnNonTranslatableSave`

## Integration test (Phase14)

`ListingCacheInvalidationIntegrationTest`:
1. Register a listing with `entity:translatable_article` tags
2. Resolve listing (cache miss → populate)
3. Resolve again (cache hit → assert same result identity)
4. Save the translatable entity with two new langcodes (`mi-tle` + `en`)
5. Assert both `entity:translatable_article:42:mi-tle` and `entity:translatable_article:42:en` tags evicted
6. Resolve listing again → cache miss → fresh content with new langcodes visible
