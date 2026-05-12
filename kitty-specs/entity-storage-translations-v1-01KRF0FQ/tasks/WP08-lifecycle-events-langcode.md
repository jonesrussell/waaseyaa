---
work_package_id: WP08
title: Lifecycle events with langcode + TranslationEvent class
dependencies:
- WP07
requirement_refs:
- FR-043
- FR-044
- FR-045
- FR-046
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T040
- T041
- T042
- T043
- T044
- T045
history: []
authoritative_surface: packages/entity/
execution_mode: code_change
owned_files:
- packages/entity/src/Event/EntityEvent.php
- packages/entity/src/Event/TranslationEvent.php
- packages/entity/src/Event/EntityEvents.php
- packages/entity-storage/src/CoordinatorLifecycleDispatcher.php
- packages/entity/tests/Event/*
- packages/entity-storage/tests/Coordinator/EventDispatch*
tags: []
agent: "claude:opus:waaseyaa-reviewer:reviewer"
shell_pid: "594610"
---

# WP08 — Lifecycle events with langcode + TranslationEvent class

## Objective

Add `?string $langcode` to `EntityEvent`, ship `TranslationEvent` subclass, declare 6 new event-name constants, and wire `CoordinatorLifecycleDispatcher` to fire translation events at the correct points in the save/delete flow.

## Context

- **Spec:** [`../spec.md`](../spec.md) §3.8 (FR-043..FR-046)
- **Contracts:** [`../contracts/lifecycle-events.md`](../contracts/lifecycle-events.md)
- **Research:** [`../research.md`](../research.md) R3, R4

## Subtasks

### T040 — Extend `EntityEvent` with `?string $langcode`

**Steps:**

1. Open `packages/entity/src/Event/EntityEvent.php`. Current shape:
   ```php
   final class EntityEvent extends Event
   {
       public function __construct(
           public readonly EntityInterface $entity,
           public readonly ?EntityInterface $originalEntity = null,
       ) {}
   }
   ```
2. **Critical:** `EntityEvent` is currently `final`. To support subclassing in T041, remove `final` from the class OR keep it final and rely on composition. **Choose: remove `final`** so `TranslationEvent` can `extend EntityEvent`. This is a minor stability concession; document in WP14.
3. Add third parameter:
   ```php
   class EntityEvent extends Event                                  // no longer final
   {
       public function __construct(
           public readonly EntityInterface $entity,
           public readonly ?EntityInterface $originalEntity = null,
           public readonly ?string $langcode = null,
       ) {}
   }
   ```

**Files:** ~15 lines net change.

### T041 — `TranslationEvent` subclass

**Steps:**

1. Create `packages/entity/src/Event/TranslationEvent.php`:
   ```php
   <?php
   declare(strict_types=1);
   namespace Waaseyaa\Entity\Event;

   use Waaseyaa\Entity\EntityInterface;

   final class TranslationEvent extends EntityEvent
   {
       public function __construct(
           EntityInterface $entity,
           string $langcode,
           ?EntityInterface $originalEntity = null,
       ) {
           parent::__construct($entity, $originalEntity, $langcode);
       }
   }
   ```
2. `TranslationEvent` is `final`; its `langcode` is required (non-nullable).

**Files:** ~30 lines.

### T042 — Six new `EntityEvents` constants

**Steps:**

1. Locate the event-name registry (search `grep -rln 'PRE_INSERT\|POST_INSERT' packages/entity/src/Event/`). Likely `EntityEvents.php` or similar.
2. Add 6 constants:
   ```php
   public const PRE_TRANSLATION_INSERT  = 'waaseyaa.entity.pre_translation_insert';
   public const POST_TRANSLATION_INSERT = 'waaseyaa.entity.post_translation_insert';
   public const PRE_TRANSLATION_UPDATE  = 'waaseyaa.entity.pre_translation_update';
   public const POST_TRANSLATION_UPDATE = 'waaseyaa.entity.post_translation_update';
   public const PRE_TRANSLATION_DELETE  = 'waaseyaa.entity.pre_translation_delete';
   public const POST_TRANSLATION_DELETE = 'waaseyaa.entity.post_translation_delete';
   ```

**Files:** `packages/entity/src/Event/EntityEvents.php` (modify, ~10 lines added).

### T043 — `CoordinatorLifecycleDispatcher` translation dispatch

**Steps:**

1. Open `packages/entity-storage/src/CoordinatorLifecycleDispatcher.php`.
2. Add methods for translation events:
   ```php
   public function dispatchPreTranslationInsert(EntityInterface $entity, string $langcode): void;
   public function dispatchPostTranslationInsert(EntityInterface $entity, string $langcode): void;
   public function dispatchPreTranslationUpdate(EntityInterface $entity, string $langcode): void;
   // etc.
   ```
3. Each method dispatches a `TranslationEvent` with the right event-name constant.
4. WP07's coordinator was updated to call these dispatch methods at the right points. T043 lands the dispatcher; T037 already inserted the call sites. Verify they line up.

**Files:** `packages/entity-storage/src/CoordinatorLifecycleDispatcher.php` (modify, ~80 lines added).

### T044 — Dispatch order tests

**Steps:**

1. Create `packages/entity-storage/tests/Coordinator/EventDispatchOrderTest.php`:
   - Test the canonical save flow per contracts/lifecycle-events.md "Dispatch order" section:
     - `PRE_UPDATE` (entity-level, langcode=null)
     - `PRE_TRANSLATION_UPDATE` (TranslationEvent, langcode='en')
     - `PRE_TRANSLATION_INSERT` (TranslationEvent, langcode='fr')
     - [persist]
     - `POST_TRANSLATION_INSERT`
     - `POST_TRANSLATION_UPDATE`
     - `POST_UPDATE`
   - Test the canonical delete flow for an entity with 3 translations.

2. Test atomicity: a listener throwing inside `PRE_TRANSLATION_INSERT` MUST roll back the entire transaction (no partial persist).

**Files:** ~250 lines of tests.

### T045 — Integration tests + payload assertions

**Steps:**

1. Create `packages/entity/tests/Event/TranslationEventTest.php`:
   - `TranslationEvent::$langcode` is publicly accessible.
   - `TranslationEvent` extends `EntityEvent`.
   - Listener registered with `EntityEvent` type catches `TranslationEvent` (inheritance).
   - Listener can narrow via `$event instanceof TranslationEvent`.

2. Integration test: create entity, add translation, save, assert `TranslationEvent` was dispatched with correct langcode.

**Files:** ~120 lines.

## Definition of Done

- [ ] `EntityEvent` accepts `?string $langcode` (third constructor param).
- [ ] `EntityEvent` is NO LONGER `final` (documented stability concession).
- [ ] `TranslationEvent` class shipped; `final`; `langcode` required.
- [ ] 6 event-name constants added.
- [ ] Coordinator dispatcher fires translation events at correct points.
- [ ] Dispatch order tests verify the canonical save and delete flows.
- [ ] Atomic transaction tests verify rollback on listener throw.
- [ ] `composer phpstan`, `composer cs-check`, `bin/check-package-layers` green.

## Risks

| Risk | Mitigation |
|---|---|
| Removing `final` from `EntityEvent` is a (minor) public-surface change. | Document in WP14. No callers were `instanceof`-narrowing against `final EntityEvent` (verify with grep). |
| Event registration via the `EntityEvents` registry needs to be the single canonical source. | Avoid raw string event names in coordinator; always use `EntityEvents::PRE_TRANSLATION_INSERT` etc. |

## Reviewer guidance

- Verify the `final` removal is documented in WP14 reconciliation.
- Verify dispatch order tests run with a real EventDispatcher (not a mock that records names only).
- Verify atomic-rollback test asserts the transaction was rolled back (no row in DB after throw).

## Implementation command

```bash
spec-kitty agent action implement WP08 --agent <name>
```

## Activity Log

- 2026-05-12T23:29:56Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=592130 – Started implementation via action command
- 2026-05-12T23:40:10Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=592130 – Lifecycle: EntityEvent extended with langcode, TranslationEvent subclass, 6 new constants, dispatch ordering tests + atomic rollback verification
- 2026-05-12T23:41:15Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=594610 – Started review via action command
- 2026-05-12T23:44:35Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=594610 – WP08 approved: EntityEvent + langcode, TranslationEvent subclass, 6 event constants, coordinator dispatch ordering tests + atomic rollback. PSR-14 silent-skip noted as known limitation.
