# Data Model: PHP 8.4 Lazy Object Hydration

**Mission**: php84-lazy-object-hydration-01KR82KZ
**Phase**: 1 (Design)

This mission introduces no new entity types and no new persisted fields. The data model is unchanged. This document records the **internal data-flow change** so reviewers and implementers share a mental model.

---

## Affected types

All entity classes registered with `EntityTypeManager`:

- `Waaseyaa\User\Entity\User`
- `Waaseyaa\Node\Entity\Node`
- `Waaseyaa\Taxonomy\Entity\Term`
- `Waaseyaa\Media\Entity\Media`
- `Waaseyaa\Path\Entity\Path`
- `Waaseyaa\Menu\Entity\Menu`
- `Waaseyaa\Note\Entity\Note`
- Any application-defined `EntityBase` subclass.

`InMemoryEntityStorage` (test fixture) does not go through `SqlEntityStorage::mapRowToEntity()` and is unaffected.

---

## Internal field-bag flow

```
SQL row (associative array from DBAL)
  + _data JSON blob (decoded once, merged in)
  ───────────────────────────────────────────
                 │
                 ▼
  ┌─────────────────────────────────────────┐
  │  EntityInstantiator::fromStorage(...)   │
  │                                         │
  │  1. Build $values from row + _data      │
  │  2. Extract entity-key fields           │
  │     ($id, $uuid, $label, …)             │
  │  3. ReflectionClass::newLazyGhost(      │
  │       fn ($entity) => $entity           │
  │           ->initializeFromValues(       │
  │               $values))                 │
  │  4. ReflectionProperty::setValue() for  │
  │     each entity-key field on the ghost  │
  │  5. Return ghost                        │
  └─────────────────────────────────────────┘
                 │
                 ▼
       Caller holds an EntityInterface
       (observably indistinguishable
        from an eager instance)
                 │
                 ├─── Reads id/uuid/label  ───→ no init
                 │
                 ├─── Reads any other field ──→ ghost initializes
                 │                              (closure runs once,
                 │                              populates EntityValues bag)
                 │
                 ├─── ->set('foo', $v)     ───→ ghost initializes, then set
                 │
                 ├─── ->save() / ->delete()──→ ghost initializes, then run
                 │                              lifecycle hooks + events
                 │
                 └─── ===  /  instanceof  ───→ no init (identity preserved)
```

---

## Storage factory flow (EntityTypeManager)

```
Today (hand-rolled lazy):
  registerEntityType(MyType, $factoryClosure)
    └─ stash $factoryClosure in array keyed by type-id

  getStorage('my_type')
    └─ if (!cached) cached = ($factoryClosure)($definition)
       return cached

After mission:
  registerEntityType(MyType, $factoryClosure)
    └─ proxy = ReflectionClass::newLazyProxy(
         EntityStorageDriverInterface::class,
         fn () => ($factoryClosure)($definition)
       )
       cache proxy by type-id

  getStorage('my_type')
    └─ return cached proxy (factory not yet invoked)

  Caller calls $proxy->load($id)
    └─ proxy materializes real storage on first method call
       and forwards subsequent calls to it
```

---

## Invariants preserved

- **Identity**: `$repo->find(1) === $repo->find(1)` within a unit of work. The identity map in `EntityRepository` keys by `(type, id)` and returns the cached instance — lazy or eager, the same object reference.
- **Type identity**: `get_class($entity)` and `(new ReflectionObject($entity))->getName()` return the same FQCN they would for an eager instance.
- **Event sequence**: `EntityRepository::save()` dispatches `PRE_SAVE` → calls `preSave()` → persists → calls `postSave()` → dispatches `POST_SAVE`. Initialization, if it fires, happens at the first non-key access — typically inside `preSave()` or persist — and is invisible to event listeners.
- **`_data` blob semantics**: `mapRowToEntity()` decodes `_data` once, merges into `$values`; the closure captures the merged array. No double-decode, no different keys.

---

## Invariants NOT preserved (intentional)

- **Constructor invocation timing**: Lazy ghosts skip the constructor at instantiation. The `EntityBase` constructor side effect (populating `EntityValues` from `$values`) is moved into a `protected` `initializeFromValues(array $values): void` method called from both the constructor (eager path) and the lazy initializer closure (lazy path).
- **Memory profile**: A lazy ghost retains the row array via the captured closure until init fires, slightly increasing peak memory. Bounded by NFR-004 (≤5% peak-memory regression on the test suite).
