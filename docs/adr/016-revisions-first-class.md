# 016 — Entity revisions as first-class framework surface

**Status:** Accepted (2026-05-11)
**Mission:** Stability charter ratification; M3 (column-backed fields) scope expansion
**Spec context:** `docs/specs/drupal-comparison-matrix.md` §6.7, §1.10, §3.1

## Context

No entity revision API exists today. Minoo's Knowledge Keeper editorial flow is currently "the policy permits the edit; the edit lands." For an editorial CMS — which is most of Drupal's installed base and most of Waaseyaa's likely future consumers — this is insufficient. For Minoo specifically, a Knowledge Keeper-edited `Teaching` needs revision history for cultural integrity reasons, not just operational ones.

Three implementation locations are possible:

- **First-class framework concern** — revision storage as part of M3, `RevisionableEntityInterface` on the stable surface.
- **Plugin / contrib package** — `waaseyaa/revisions` as a separable framework package.
- **App-level** — apps roll their own.

Revisions interact deeply with storage (every save creates a row in a `*__revisions` table or equivalent), with access (per-revision access decisions), with lifecycle events (revision creation timing), and with the future Views/listing pipeline (filter by latest revision). Layering them on top of an existing storage system retroactively is substantially more expensive than designing for them up-front.

**Content moderation / workflow** (states + transitions + approval queues) is a separate concern that consumes revisions. A future ADR will address it. This ADR governs the **substrate**, not the editorial UI on top.

## Options considered

### A. First-class framework concern (CHOSEN)

`RevisionableEntityInterface` is part of the entity package's stable surface. Entity types opt in by implementing the interface and declaring a revision key in their `EntityType`. Revision storage is co-designed with M3 column-backed fields. Per-revision access falls out of the existing policy system.

### B. Plugin / contrib `waaseyaa/revisions` package

Revisions ship as a separable package that apps install. Lower framework footprint. Rejected: revisions touch storage, lifecycle events, queries, and access — four of the five most central framework concerns. A "plugin" that touches four core concerns is not a plugin; it's a fork-by-another-name.

### C. App-level

Each app implements its own revision strategy. Rejected: forfeits cross-app tooling (admin UI, listings of "recent revisions"), encourages divergent storage schemas, and means migration tools (admin import, theme switches) cannot rely on revision semantics.

## Decision

Revisions are a **first-class framework concern**. Entity types opt in per type.

### Opt-in mechanism

```php
final class Teaching extends ContentEntityBase implements RevisionableEntityInterface
{
    // existing class body
}
```

`EntityType` declares the revision key:

```php
new EntityType(
    id: 'teaching',
    entityKeys: [
        'id' => 'tid',
        'uuid' => 'uuid',
        'revision' => 'vid',
        'label' => 'title',
        ...
    ],
    revisionable: true,
)
```

### Storage shape

Co-designed with M3 column-backed fields (ADR 010). The revision-storage schema is:

- A primary entity table (`teaching`) with one row per entity, pointing at the **current revision id**.
- A revision table (`teaching__revision`) with one row per revision, carrying all field values for that revision.
- Field values live in the column-backed schema (M3) or blob (`sql-blob`) per backend selection; revisions ride whichever backend the entity uses.

For the `sql-blob` backend (M3 pre-migration), the revision table carries `_data`. For `sql-column`, the revision table carries the full column set.

### API on the stable surface

```php
interface RevisionableEntityInterface extends EntityInterface
{
    public function revisionId(): int|string|null;
    public function isCurrentRevision(): bool;
    public function revisionLog(): ?string;
    public function revisionAuthor(): ?int;
    public function revisionCreatedAt(): ?\DateTimeImmutable;
}

interface RevisionableEntityStorageInterface extends EntityStorageInterface
{
    public function loadRevision(string $entityType, int|string $revisionId): ?RevisionableEntityInterface;
    public function listRevisions(EntityInterface $entity): iterable;
    public function setCurrentRevision(EntityInterface $entity, int|string $revisionId): void;
}
```

### Revision creation semantics

By default, every `save()` on a revisionable entity creates a new revision and points the current-revision pointer at it. Apps can opt out per save (e.g. "this is a minor edit, don't make a revision") via an explicit save-context flag — but the **default is "yes, revision"**. This matches Drupal's default and prevents accidental history loss.

Old revisions are not auto-pruned. A `RevisionPruner` service is provided for apps that need pruning policies; it ships disabled.

### Access

Per-revision access is the existing `GateInterface` with a new operation `view_revision`. Policies opt in:

```php
#[PolicyAttribute(entityType: 'teaching', operations: ['view', 'edit', 'view_revision'])]
final class TeachingAccessPolicy { ... }
```

If a policy doesn't implement `view_revision`, access falls back to `view`.

### Lifecycle events

Revision creation does **not** introduce new lifecycle event names (consistent with ADR 011's minimalism). `BeforeSaveEvent` / `AfterSaveEvent` fire once per `save()`. Subscribers wanting to act on revision creation specifically read `$entity->revisionId()` and compare against pre-save state. This keeps the event surface small.

### Migration of existing entity types

Entity types that are non-revisionable today (all of them) continue to work unchanged. Opting in is a per-type decision:

1. Add `implements RevisionableEntityInterface`.
2. Add `revisionable: true` to the EntityType.
3. Run a migration to add the revision table.
4. Backfill: the current row becomes the first revision; current-revision pointer is set.

This is a non-trivial migration but is per-type, voluntary, and reversible.

### What is NOT in this ADR

- **Content moderation workflows.** States, transitions, approval queues, scheduled publishing — separate future ADR.
- **Revision UI.** Admin views of revision history, compare-two-revisions, revert — app-layer concern; framework provides only the data.
- **Translations of revisions.** Per-revision-per-language storage interacts with the future translation ADR. Revisionable + translatable entity types are out of scope for v0.x; addressed when translatable lands.

## Consequences

- **M3 scope expands.** Column-backed fields must design for the revision-table sibling now, not retrofit later. Net cost: ~15–25% larger M3, vastly cheaper than retrofitting revisions onto a non-revisionable storage layer.
- **The framework gains an opt-in revision surface that all editorial use cases can build on.** Workflows, moderation, scheduled publishing become future apps/extensions.
- **The default-create-revision-on-save choice is defensible but opinionated.** Apps unfamiliar with this default may be surprised by revision-table growth. Documented loudly.
- **Per-revision access via policies stays declarative.** No new policy machinery; one new operation name.
- **Beta gate addition.** Charter §3.2 beta entry criteria should add: "RevisionableEntityInterface is stable surface and at least one revisionable entity type ships." Otherwise "beta" misleads consumers about editorial capability.

## References

- Matrix: `docs/specs/drupal-comparison-matrix.md` §1.10, §3.1, §6.7.
- Charter: `docs/specs/stability-charter.md` §3.2 (beta criteria — to be amended), §5.3 (entity/storage rules).
- Audit: `waaseyaa/minoo/docs/audits/2026-05-11-framework-app-audit.md` F1, M3.
- Related ADRs: 010 (storage backends carry revision tables), 011 (no new lifecycle events for revisions), 015 (listing pipeline filters by current revision).
- Future ADRs:
  - Content moderation workflows (states + transitions).
  - Per-field translation (`langcode` + revisionable interaction).
