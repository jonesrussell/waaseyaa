# WP08 Review — Cycle 1 (APPROVED)

**Mission:** entity-storage-v2-01KRCDDC
**Work package:** WP08 — RevisionableEntityStorageInterface + load/list/setCurrent
**Commit reviewed:** `d575dfe55`
**Branch:** `kitty/mission-entity-storage-v2-01KRCDDC-lane-a`
**Reviewer:** opus (cycle 1)
**Date:** 2026-05-12

---

## Verdict: **APPROVE**

All T040–T046 acceptance criteria satisfied. All gates green. Full suite 7676/7676.
The implementation is clean, well-documented, and respects the WP04/WP07 contracts
without breaking byte-identity (WP03) or partial-save (WP04) regression surfaces.

---

## Acceptance criteria — line-by-line

| # | Criterion | Status | Notes |
|---|---|---|---|
| 1 | T040 — Interface signatures match `contracts/revisionable-entity.md` §2 | OK | Three methods exact: `loadRevision(EntityTypeInterface, int\|string)`, `listRevisions(RevisionableEntityInterface)`, `setCurrentRevision(RevisionableEntityInterface, int\|string)`. `@api` on the interface and on each method. Namespace `Waaseyaa\EntityStorage`. |
| 2 | T041 — `loadRevision` in blob + column storages | OK | Both query `<table>__revision` by vid via the query builder, hydrate via shared `RevisionRowHydrator`. `isCurrentRevision()` derives from `($vid === (int) $currentVid)` — see vid-type note below. |
| 3 | T042 — `listRevisions` lazy generator, DESC by `revision_created_at` | OK | Both backends use `yield` over a query-builder iterator. No pagination. Returns empty iterable when `entityId === null`. |
| 4 | T043 — `setCurrentRevision` transactional, dispatches Before/AfterSave on success | OK | Validates target vid exists → throws `InvalidArgumentException` if not. Dispatches `BeforeSaveEvent` → opens transaction → `UPDATE primary SET vid = ?` → `commit()` in try; `rollBack()` + rethrow in catch. `AfterSaveEvent` dispatched **after** the try/catch block, which is unreachable on rollback because the catch rethrows. Gate correct. |
| 5 | T044 — Coordinator honors `SaveContext::$withoutNewRevision` | OK | ~12-line delta in `EntityStorageCoordinator::write()`. Derives `$effectiveIsNewRevision = false` only when both `withoutNewRevision === true` AND `entityType->isRevisionable()`. Explicit caller override of `$isNewRevision = false` still wins (documented). Non-revisionable path untouched. WP04 partial-save semantics intact. |
| 6 | T045 — `RevisionPruner` scaffold inert | OK | `final class`, `private bool $enabled = false`, **no setter, no named constructor mutator**. The only path through `prune()` returns `RevisionPruningReport::disabled()`. Active branch is unreachable and marked `@codeCoverageIgnore`. Policy + Report are value objects with readonly properties. |
| 7 | T046 — All 6 test scenarios | OK | 14/14 in `tests/Integration/Revisions/RoundTripTest.php`. Covers all 5 mandatory scenarios + `after_save_does_not_fire_when_setCurrentRevision_fails` (Risk-#2 mirror) + pruner scaffold smoke. |
| 8 | Code sharing via composition (not service locator / static) | OK | `RevisionRowHydrator` is `final`, takes `DBALDatabase` + `EntityTypeInterface` in ctor, no static state, marked `@internal`. Composition is clean. |
| 9 | Namespace + layer + `@api` discipline | OK | All new public symbols `@api`. `Waaseyaa\EntityStorage` (L1). `RevisionRowHydrator` is `@internal`, correct. |
| 10 | No `psr/log`, no `Illuminate\*`, no service locators, `declare(strict_types=1)`, `final class` on concretes | OK | Verified via diff scan; all six new files use `declare(strict_types=1)` and `final class`. PSR `EventDispatcherInterface` used (correct — that's `psr/event-dispatcher`, allowed). |
| 11 | No spec §1.2/§2.2 non-goals | OK | No admin UI, no auto-pruning, no moderation, no per-field translation. Pruner is inert. |

---

## Deep scrutiny — items (a)–(f) from the review brief

### (a) AfterSave-on-failure gate

**PASS.** The dispatch is structurally gated:

```php
$transaction = $this->database->transaction();
try {
    $this->database->update($primaryTable)->fields(['vid' => (int) $revisionId])->...->execute();
    $transaction->commit();
} catch (\Throwable $e) {
    $transaction->rollBack();
    throw $e;            // <-- exits method before reaching dispatch
}

if ($this->dispatcher !== null) {
    $this->dispatcher->dispatch(new AfterSaveEvent($entity, $saveContext, false));
}
```

The catch rethrows, so control never reaches the AfterSave dispatch on failure.
The `after_save_does_not_fire_when_setCurrentRevision_fails` test asserts this and passes.

### (b) Lifecycle dispatch path — does it reuse `CoordinatorLifecycleDispatcher`?

**No — and that's defensible here, but worth noting.** The implementer dispatches
`BeforeSaveEvent`/`AfterSaveEvent` directly inside each storage class rather than
delegating to the WP04 `CoordinatorLifecycleDispatcher`. The reviewer brief flagged
two dispatch sites as a potential smell.

However, `CoordinatorLifecycleDispatcher::save()` is structured around the
multi-backend fan-out (groups, primaryId, alternates, partial-save tracking).
`setCurrentRevision()` is a single-table primary-row UPDATE, not a fan-out — there
is no group structure to pass. Reusing the full dispatcher would require either
synthesizing an empty group set (ugly) or adding a dedicated single-write entry
point on the dispatcher. Both routes increase coupling between revision storage
and the coordinator.

**Accept as-is.** The dispatch logic is small (8 lines per class), the AfterSave
gate is correct, and the test coverage proves it. A later refactor could extract a
`LifecycleDispatchHelper` if a third dispatch site appears (e.g. revision rollback,
revision moderation). Tracked here as a future-readability note, not a blocker.

### (c) Vid type discipline — `int|string`

**Minor gap, accepted.** The interface declares `int|string $revisionId`. The
implementation forces `(int)` at three sites:

- `RevisionRowHydrator::fetchCurrentVid()` returns `?int`.
- `hydrateRevisionRow()` compares `$vid === (int) $currentVid`.
- `setCurrentRevision()` writes `['vid' => (int) $revisionId]` and compares row vid via `(int)`.

This works for SQLite/MySQL/PostgreSQL where the `vid` column emitted by
`RevisionTableBuilder` (WP07) is `INTEGER`/`BIGINT`. String vids (e.g. UUID-keyed
revisions) would be lossy. There is no string-vid backend in scope for this
milestone and no test currently exercises one.

**Accept.** Flag for spec §8 (type mapping) if/when a string-keyed revision
backend appears. The interface signature is correct; only the SQL impls are
narrower than the contract allows.

### (d) `RevisionPruner` inertness

**PASS.** Source-verified:
- `final class RevisionPruner` — cannot be subclassed.
- `private bool $enabled = false` — no setter, no with-method, no named
  constructor that flips it.
- Constructor only accepts `RevisionPruningPolicy` (default empty policy).
- `prune()` always returns `RevisionPruningReport::disabled()`; the second
  `return` is `@codeCoverageIgnore` and unreachable.

There is no path to set `$enabled = true` short of editing the class. Future WP
will add a real constructor flag or factory.

### (e) `RevisionableEntityInterface` vs legacy `RevisionableInterface`

Not exercised in this WP — `RevisionableArticleEntity` fixture extends the same
`RevisionableEntityTrait` set up in WP07 and works with the new hydrator. No
clash observed in tests or static analysis.

### (f) WP03 byte-identity + WP04 events regression

**PASS.** Targeted runs:
- `packages/entity-storage/tests/Integration/BehaviorIdentity/` — green.
- `packages/entity-storage/tests/Integration/Events/` — 13/13 green.
- `packages/entity-storage/tests/Integration/Revisions/` — 14/14 green.
- Full `packages/entity-storage/tests/` — 437/437 green.

The T044 coordinator delta is guarded by `entityType->isRevisionable()`, so the
non-revisionable sql-blob path is untouched and BehaviorIdentity is preserved.

---

## Gate spot-checks

| Gate | Result |
|---|---|
| `composer cs-check` | exit 0 |
| `composer phpstan` | `[OK] No errors`, exit 0 |
| `bin/check-package-layers` | OK — layer constraints satisfied |
| `bin/check-composer-policy` | OK: Composer policy checks passed |
| `phpunit packages/entity-storage/tests/Integration/Revisions/` | 14/14, 39 assertions |
| `phpunit packages/entity-storage/tests/Integration/BehaviorIdentity/` | green |
| `phpunit packages/entity-storage/tests/Integration/Events/` | 13/13, 46 assertions |
| `phpunit packages/entity-storage/tests/` | 437/437, 1011 assertions |
| `phpunit` (full suite) | **7676/7676**, 18616 assertions, 0 failures |

---

## Non-blocking notes for follow-up WPs

1. **Dispatch site duplication.** If WP09 (per-revision access) or a later revision
   rollback feature adds a third dispatch site, consider extracting a small
   `LifecycleDispatchHelper` shared by the storages — keeps the AfterSave-on-failure
   pattern documented in one place.
2. **Vid type breadth.** The hydrator narrows `int|string` vid to `int` at three
   call sites. Document this in the contract as a current backend constraint, or
   widen the impls if a string-vid backend lands.
3. **`@codeCoverageIgnore` in `RevisionPruner::prune()`.** The unreachable second
   return is acceptable scaffolding; it should be removed in the WP that wires
   activation, not lingering forever.

---

## Approval

WP08 is approved for cycle 1. Move to `approved`.

Note for orchestrator: `setCurrentRevision` directly dispatches Before/AfterSave
rather than routing through `CoordinatorLifecycleDispatcher`. This was scrutinised
and accepted because the operation is a single-table UPDATE (not a multi-backend
fan-out) and the AfterSave-on-failure gate is correctly structured.
