# Contract — Lifecycle Events

**Owning WP**: WP04.
**Source**: spec §3.5, §6; ADR 011.
**Stable surface**: yes (charter §5.3).

---

## Events

All four events are dispatched by `EntityStorageCoordinator`, never by backends.

```php
namespace Waaseyaa\EntityStorage\Event;

/**
 * @api — marker for all lifecycle events. Charter §5.3.
 */
interface EntityLifecycleEventInterface
{
    public function entity(): \Waaseyaa\Entity\EntityInterface;
}

/** @api */ final class BeforeSaveEvent implements EntityLifecycleEventInterface { ... }
/** @api */ final class AfterSaveEvent  implements EntityLifecycleEventInterface { ... }
/** @api */ final class BeforeDeleteEvent implements EntityLifecycleEventInterface { ... }
/** @api */ final class AfterDeleteEvent  implements EntityLifecycleEventInterface { ... }
```

Each event exposes:
- `entity(): EntityInterface` — the entity being saved/deleted (post-mutation snapshot for AfterSave).
- Save events additionally expose `saveContext(): SaveContext` and `isNewRevision(): bool`.

## Dispatch points (normative)

Save:
1. `BeforeSaveEvent` — before ANY backend write. Subscribers may throw `AbortOperationException` to halt.
2. Backend writes execute (primary first, alternates in registration order).
3. `AfterSaveEvent` — ONLY if all writes succeeded. NOT dispatched on `PartialSaveException`.

Delete:
1. `BeforeDeleteEvent` — before ANY backend delete. Subscribers may throw `AbortOperationException`.
2. Backend deletes execute.
3. `AfterDeleteEvent` — ONLY if all deletes succeeded.

## Abort semantics

```php
namespace Waaseyaa\EntityStorage\Event;

/**
 * @api
 *
 * Thrown by Before* subscribers to halt the operation. The exception propagates
 * through {@see EntityStorageCoordinator::save()} / ::delete() to the caller.
 * No After* event fires. No backend writes occur after this is thrown.
 */
final class AbortOperationException extends \RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly ?string $subscriberFqcn = null,
    ) { parent::__construct($reason); }
}
```

## Logging

A structured log line MUST be emitted on the `entity.lifecycle` channel for each dispatch:

```
channel: entity.lifecycle
level:   info (debug for high-volume entity types)
fields:  event_class, entity_type_id, entity_id, save_context, duration_ms, outcome (ok|aborted|partial_save)
```

## Test surface

- Coordinator integration tests verify: order of dispatch, abort halts, partial-save suppresses After*.
- Subscribers under test MUST be added via Symfony EventDispatcher (no global registration tricks).
