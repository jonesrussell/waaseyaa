---
work_package_id: WP07
title: ListingCacheInvalidator + AfterSaveEvent/AfterDeleteEvent patch + SqlStorageDriver backfill
dependencies:
- WP03
- WP06
requirement_refs:
- FR-038
- FR-039
- FR-040
- FR-041
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T033
- T034
- T035
- T036
- T037
history: []
authoritative_surface: packages/listing/
execution_mode: code_change
owned_files:
- packages/listing/src/ListingCacheInvalidator.php
- packages/foundation/src/Event/AfterSaveEvent.php
- packages/foundation/src/Event/AfterDeleteEvent.php
- packages/foundation/tests/Unit/Event/AfterSaveEventAffectedLangcodesTest.php
- packages/foundation/tests/Unit/Event/AfterDeleteEventAffectedLangcodesTest.php
- packages/entity-storage/src/SqlStorageDriver.php
- packages/entity-storage/tests/Unit/SqlStorageDriverAffectedLangcodesTest.php
- packages/listing/tests/Unit/ListingCacheInvalidatorTest.php
tags: []
agent: "claude:sonnet:python-implementer:implementer"
shell_pid: "32441"
---

## Objective

Wire automatic cache invalidation. Three coordinated changes:
1. `AfterSaveEvent` + `AfterDeleteEvent` (in `packages/foundation`) gain optional `?array $affectedLangcodes` property (additive surface patch).
2. `SqlStorageDriver` (in `packages/entity-storage`) backfills the array on translatable save/delete paths.
3. `ListingCacheInvalidator` (in `packages/listing`) subscribes to both events at priority=100 and emits tag invalidations.

This WP crosses three packages — coordinate carefully. All changes are additive.

## Context

- See R-07 / §11.7 in research.md for the decision rationale.
- See `contracts/lifecycle-event-patch.md` for the full event surface contract.
- Event-listener registration is via the foundation's `AsEventListener` attribute (mirror existing patterns).
- Best-effort invalidation: cache backend errors caught + logged + continue, per FR-040.

## Subtask details

### T033 — Patch `AfterSaveEvent` + `AfterDeleteEvent` (foundation)

**Steps:**
1. Read `packages/foundation/src/Event/AfterSaveEvent.php`.
2. Add an optional `readonly ?array $affectedLangcodes = null` property/parameter to the constructor (additive — third positional / named param).
3. Update class docblock to document the new field: "List of langcodes touched in this save. Null = inferred via `$entity->activeLangcode()` by consumers."
4. Mirror in `AfterDeleteEvent.php`.

**Files:** Both event files (modified, +5-10 lines each).

**Backwards compatibility:** Existing callers passing only positional `(entity, originalEntity)` compile unchanged. Existing listeners reading `$event->entity` see no change.

### T034 — Backfill `affectedLangcodes` in `SqlStorageDriver`

**Purpose:** When the M-006-shipped translatable write path saves rows for multiple langcodes, the driver knows exactly which langcodes were touched. Capture that into the dispatched event.

**Steps:**
1. Read `packages/entity-storage/src/SqlStorageDriver.php`.
2. Find the `save()` method that dispatches `AfterSaveEvent`.
3. Add logic:
   - Initialize `$affectedLangcodes = null` before save
   - If entity is `TranslatableInterface`:
     - Determine which langcodes are written in this save:
       - For new entity: all declared langcodes
       - For updated entity: the langcodes that differ from the original (or all if write semantics dictate)
     - Set `$affectedLangcodes = [...]` (sorted, unique list)
   - When dispatching `AfterSaveEvent`, pass `$affectedLangcodes` as the third arg
4. Same logic in `delete()` for `AfterDeleteEvent`:
   - For translatable entity delete, `$affectedLangcodes = $entity->getTranslationLanguages()` (all langcodes that existed before delete)
5. For non-translatable entities: leave `$affectedLangcodes` as `null` (no per-langcode tagging needed).

**Files:** `packages/entity-storage/src/SqlStorageDriver.php` (modified, +20-30 lines).

**Test:** `SqlStorageDriverAffectedLangcodesTest.php`:
- `saveTranslatableEntityBackfillsAffectedLangcodes` — fixture entity with `['en', 'mi-tle']`, save → event carries both
- `saveNonTranslatableLeavesAffectedLangcodesNull`
- `deleteTranslatableBackfillsAllExistingLangcodes`

### T035 — `ListingCacheInvalidator` class

**Steps:**
1. Create `packages/listing/src/ListingCacheInvalidator.php`:
   - `final class ListingCacheInvalidator`
   - Constructor: `__construct(private readonly TaggedCacheInterface $cache, private readonly LoggerInterface $logger = new NullLogger())`
   - Method `public function onAfterSave(AfterSaveEvent $event): void`
   - Method `public function onAfterDelete(AfterDeleteEvent $event): void`
2. Both methods follow the algorithm in `contracts/lifecycle-event-patch.md` §"Consumer behaviour":
   ```php
   public function onAfterSave(AfterSaveEvent $event): void
   {
       $entity = $event->entity;
       $tags = [
           "entity:{$entity->getEntityTypeId()}",
           "entity:{$entity->getEntityTypeId()}:{$entity->id()}",
       ];
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
               $this->logger->warning('cache invalidation failed', [
                   'tag' => $tag,
                   'exception' => $t::class,
                   'message' => $t->getMessage(),
               ]);
           }
       }
   }
   ```
3. `onAfterDelete` is structurally identical.

**Files:** `packages/listing/src/ListingCacheInvalidator.php` (new, ~80 lines).

### T036 — Subscriber wiring

**Purpose:** Register the invalidator with the framework's event dispatcher at priority=100.

**Steps:**
1. Add the `#[AsEventListener]` attribute to `ListingCacheInvalidator` methods (or equivalent foundation event-subscription mechanism). Check how existing event subscribers (e.g., `AuditLogHandler`) are registered for the canonical pattern.
2. Set priority=100 on both `onAfterSave` and `onAfterDelete` listeners.
3. The actual DI wiring (where this is registered) happens in WP11 ServiceProvider — for this WP, the attribute alone is sufficient if the framework auto-discovers via attribute scan.

**Files:** Continued in `ListingCacheInvalidator.php`.

### T037 — Unit + integration tests

**Steps:**
1. `packages/foundation/tests/Unit/Event/AfterSaveEventAffectedLangcodesTest.php`:
   - `acceptsAffectedLangcodesParameter`
   - `defaultsAffectedLangcodesNull`
   - `existingPositionalConstructionStillCompiles` (BC test)
2. Mirror in `AfterDeleteEventAffectedLangcodesTest.php`.
3. `packages/listing/tests/Unit/ListingCacheInvalidatorTest.php`:
   - `emitsEntityTypeTagOnSave`
   - `emitsEntityTypeIdTagOnSave`
   - `emitsLangcodeTagPerAffectedLangcode` (translatable, with affectedLangcodes set)
   - `fallsBackToActiveLangcodeWhenAffectedLangcodesNull` (translatable, null backfill)
   - `omitsLangcodeTagForNonTranslatable`
   - `continuesOnCacheBackendError` (mock cache throws; assert subsequent tags still attempted; logger called)
4. `packages/entity-storage/tests/Unit/SqlStorageDriverAffectedLangcodesTest.php`:
   - Three cases per T034

**Files:** All test files (~250 lines total).

## Test strategy

- Unit tests for each isolated piece
- Integration test that exercises the full save → event-dispatch → invalidate-by-tag chain lives in WP11 (`ListingCacheInvalidationIntegrationTest`)

## Definition of Done

- [ ] AfterSaveEvent + AfterDeleteEvent gain `affectedLangcodes` property (additive)
- [ ] SqlStorageDriver backfills the property on translatable saves
- [ ] ListingCacheInvalidator subscribes at priority=100 and emits expected tags
- [ ] BC test confirms existing positional event construction compiles
- [ ] Cache-backend error handling continues to subsequent tags + logs
- [ ] All 3 packages' tests pass (`packages/foundation`, `packages/entity-storage`, `packages/listing`)
- [ ] `composer cs-check` + `composer phpstan` + `bin/check-package-layers` green

## Risks

| Risk | Mitigation |
|---|---|
| Adding constructor parameter to AfterSaveEvent breaks subclasses | Search codebase for any class extending AfterSaveEvent; the existing class is `final readonly` so no subclasses possible |
| SqlStorageDriver backfill logic for "which langcodes were touched" is subtle on partial updates | Default for partial-update case: all langcodes the entity currently has (i.e., `getTranslationLanguages()`). Conservative — emits one extra tag but never misses |
| Event listener priority not honored | Verify by integration test (WP11) — order assertion |
| `affectedLangcodes` array contains duplicates or unsorted | Sort + unique inside the driver before passing to the event |

## Reviewer guidance

- Verify event surface additions are TRULY additive (default = null; existing call sites compile unchanged). Run `grep -rn 'new AfterSaveEvent' packages/` and confirm every call site still compiles.
- Verify `SqlStorageDriver`'s backfill logic doesn't break non-translatable saves (path branches cleanly).
- Verify the invalidator's error handling continues through ALL tags even if early ones fail (the `try` is inside the loop, not around it).
- Verify priority=100 is set; ideally pinned by a unit test checking `\ReflectionMethod::getAttributes()`.

## Implementation command

```bash
spec-kitty agent action implement WP07 --agent <name>
```

## Activity Log

- 2026-05-16T20:25:18Z – claude:sonnet:python-implementer:implementer – shell_pid=32441 – Started implementation via action command
- 2026-05-16T20:35:49Z – claude:sonnet:python-implementer:implementer – shell_pid=32441 – WP07 ready: cache invalidation wired via lifecycle events. 8 files / 5 subtasks T033-T037. AfterSaveEvent + AfterDeleteEvent gain additive affectedLangcodes property (backwards compatible — all existing dispatch sites compile unchanged). Backfill landed in CoordinatorLifecycleDispatcher (M-006 translatable write path); WP07 owned_files listed SqlStorageDriver but that's a pure I/O layer that does not dispatch events — deviation documented in commit body. Test gates: entity-storage 558, listing 216, cache 172, foundation 969 (all passing). cs-check, phpstan, check-composer-policy, check-package-layers all green. Commit: d81b32781.
