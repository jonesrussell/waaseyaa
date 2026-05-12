# Contract: Lazy / Eager Hydration Parity

**Mission**: php84-lazy-object-hydration-01KR82KZ
**Test file (to be created)**: `packages/entity-storage/tests/Contract/LazyHydrationParityContractTest.php`
**Companion test**: `packages/entity/tests/Unit/EntityTypeManagerLazyStorageTest.php`

This contract is **internal** — it has no HTTP, GraphQL, or CLI surface. It describes the observable equivalence the implementation must guarantee between a lazy ghost and an eager instance materialized from the same data.

---

## Setup

For each test method, materialize two instances of the same entity type from the same row:

```php
$row = ['id' => 42, 'uuid' => '…', 'label' => 'Example', 'body' => '…', '_data' => '{"meta":1}'];
$lazy  = $instantiator->fromStorage($entityType, $row); // produces lazy ghost
$eager = $eagerFallback->fromStorage($entityType, $row); // test-only eager constructor for parity check
```

The `$eagerFallback` is a test-only `EntityInstantiator` subclass that bypasses `newLazyGhost` and constructs the entity eagerly. It exists solely for parity testing.

---

## Assertions (per entity type: User, Node, Term, anonymous EntityBase fixture)

### A. Method outputs

| Method | Lazy ghost == Eager instance |
| --- | --- |
| `id()` | yes (no init triggered for ghost) |
| `uuid()` | yes (no init triggered for ghost) |
| `label()` | yes (no init triggered for ghost) |
| `bundle()` | yes |
| `getEntityTypeId()` | yes (no init triggered) |
| `getKeys()` | yes (no init triggered) |
| `isNew()` | yes — both `false` after materializing from storage |
| `toArray()` | yes (init triggered for ghost; result identical) |
| `JsonSerializable::jsonSerialize()` | yes (init triggered for ghost; result identical) |

### B. Identity

```php
$a = $repo->find(42);
$b = $repo->find(42);
self::assertSame($a, $b);            // identity map preserved
self::assertInstanceOf(User::class, $a); // type identity preserved
self::assertSame(User::class, get_class($a));
```

### C. Lifecycle event sequence

Subscribe a recording listener to `EntityEvents::PRE_SAVE` and `EntityEvents::POST_SAVE`. Save a lazy ghost. Save an eager instance. Assert:

```php
$lazyTrace  = ['preSave', 'PRE_SAVE_event', 'persist', 'POST_SAVE_event', 'postSave'];
$eagerTrace = ['preSave', 'PRE_SAVE_event', 'persist', 'POST_SAVE_event', 'postSave'];
self::assertSame($eagerTrace, $lazyTrace);
```

The same applies for delete: `preDelete → PRE_DELETE → persist → POST_DELETE → postDelete`.

### D. Init triggering

```php
$ghost = $instantiator->fromStorage($entityType, $row);
LazyInitCounter::reset();

$ghost->id(); $ghost->uuid(); $ghost->label();
self::assertSame(0, LazyInitCounter::$invocations); // key reads do NOT init

$ghost->get('body');
self::assertSame(1, LazyInitCounter::$invocations); // first non-key read inits

$ghost->get('body');
$ghost->toArray();
self::assertSame(1, LazyInitCounter::$invocations); // subsequent reads do NOT re-init
```

### E. Save without read

```php
$ghost = $instantiator->fromStorage($entityType, $row);
LazyInitCounter::reset();
$repo->save($ghost);
self::assertGreaterThanOrEqual(1, LazyInitCounter::$invocations); // save touches state, init fires
```

This is acceptable. Saves are not the laziness target.

---

## EntityTypeManager deferral contract

**Test file**: `packages/entity/tests/Unit/EntityTypeManagerLazyStorageTest.php`

Register three entity types (A, B, C) with factory closures that increment named counters. Ask for storage of A only.

```php
$manager->getStorage('A');
self::assertSame(1, $factoryAInvocations);
self::assertSame(0, $factoryBInvocations);
self::assertSame(0, $factoryCInvocations);

$storageB = $manager->getStorage('B');           // returns proxy
self::assertSame(0, $factoryBInvocations);       // factory not yet invoked
$storageB->load(1);                              // first method call materializes
self::assertSame(1, $factoryBInvocations);
```
