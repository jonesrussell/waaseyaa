# 011 — Entity lifecycle events on stable surface

**Status:** Accepted (2026-05-11)
**Mission:** Stability charter ratification
**Spec context:** `docs/specs/drupal-comparison-matrix.md` §6.2, §1.1; `docs/specs/stability-charter.md` §4.4

## Context

The framework has no documented stable contract for entity CRUD events. Apps that need cross-cutting concerns (audit logging, search index invalidation, cache tag clearing, downstream notifications) either subclass `SqlEntityStorage` or scatter the logic across every controller that mutates entities.

Drupal solves this with a rich hook system (`hook_entity_presave`, `hook_entity_insert`, `hook_entity_update`, `hook_entity_delete`, `hook_entity_predelete`, `hook_entity_load`, `hook_entity_view`, `hook_entity_access`) plus an `EntityHookEvent` family on the event dispatcher. The full Drupal surface is the result of two decades of accretion; equivalence is not the goal.

Charter §4.4 already names the `entity.deprecation` log channel. Normal-operation events need an equivalent stable surface.

## Options considered

### A. Drupal-parity (8+ hooks/events)

Predelete, presave, insert, update, delete, load, view, access. Comprehensive; large stable surface; high cognitive cost; encourages bloat. Rejected.

### B. Single generic event (`entity.changed`)

One event with an operation discriminator. Subscribers filter by op. Compact, but loses type safety on payloads; subscribers must know what fields are valid for which op. Rejected.

### C. Minimal typed lifecycle (CHOSEN)

Four events on stable surface, each with a typed payload class:

- `entity.before_save` — fires before any persistence call. Subscribers can validate or cancel via exception.
- `entity.after_save` — fires after successful persistence. Carries the saved entity and whether the operation was an insert or update.
- `entity.before_delete` — fires before any delete call. Subscribers can cancel via exception.
- `entity.after_delete` — fires after successful delete.

Apps and extensions may define additional entity-related events under their own namespace.

## Decision

Four events on stable surface. Each is a typed event class implementing a marker interface `Waaseyaa\Entity\Event\EntityLifecycleEventInterface`. Dispatched via the foundation event dispatcher.

### Event classes

```
Waaseyaa\Entity\Event\BeforeSaveEvent
Waaseyaa\Entity\Event\AfterSaveEvent
Waaseyaa\Entity\Event\BeforeDeleteEvent
Waaseyaa\Entity\Event\AfterDeleteEvent
```

Each carries:

- `entity()` — the `EntityInterface` instance.
- `operationContext()` — the in-flight operation (entity type id, bundle, originating actor if known).
- `isInsert(): bool` / `isUpdate(): bool` (save events only).

Cancellation: throwing any `Waaseyaa\Entity\Event\AbortOperationException` from a `Before*` subscriber aborts the operation. The exception propagates with a structured reason. `After*` events cannot abort.

### What is NOT on stable surface

- **Per-bundle event names.** Apps that want bundle-specific subscribers filter inside the handler. The framework will not multiply event names by bundle (Drupal does; the surface explodes).
- **Field-level events.** Field-set / field-validate are deliberately omitted. Field validation runs through `FieldDefinition::validators()` declaratively, not via events.
- **Load and view events.** Drupal's `hook_entity_load` and `hook_entity_view` are useful but encourage tight coupling. Apps that need pre-view mutation use ssr template hooks or controller-level decorators. If demand emerges, a future ADR may add `entity.after_load` as provisional surface.
- **Access events.** Access is a policy concern, not an event concern. Access decisions go through `GateInterface`, not subscribers.

### Per-backend behavior (ref ADR 010)

Lifecycle events fire at the **coordinator** layer (`EntityStorage`), not per backend. A save that fans out to `sql-column` + `vector` backends fires `BeforeSaveEvent` once before the fan-out and `AfterSaveEvent` once after both succeed. If one backend fails after another has committed, the coordinator surfaces a structured `PartialSaveException`; recovery is the operator's concern, not the event subscriber's.

### Log channel

Lifecycle events emit on the `entity.lifecycle` log channel at `debug` level when `WAASEYAA_LOG_LEVEL=debug`. Useful for diagnosing "why didn't my subscriber fire" without subscribing a test handler.

## Consequences

- **Stable surface gains four event classes.** Subject to charter §4 deprecation rules; renames require shims and notices.
- **Audit logging, search reindex, cache invalidation become declarative.** Apps stop subclassing storage for these concerns.
- **Cross-backend save semantics need careful spec.** Partial-save error path is a real surface; see ADR 010 consequences.
- **Drupal-style `hook_entity_view`-shaped use cases are not directly supported.** The ssr/template layer handles them.

## References

- Matrix: `docs/specs/drupal-comparison-matrix.md` §6.2, §1.1 ("Entity hooks/events").
- Charter: `docs/specs/stability-charter.md` §4.4 (log channels), §5.3 (entity surface).
- Related ADRs: 010 (storage coordinator dispatches events), 015 (Views cache-invalidation listens on AfterSave/AfterDelete), 016 (revision creation is an internal call within `Before/AfterSave` — does not introduce new events).
