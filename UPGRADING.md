# Upgrading

## 2026-04-20 - Entity-type collision guard for canonical group types

Framework packages now fail loudly when a consumer re-registers an entity type id that is already owned by the framework. The registry throws `Waaseyaa\Entity\Exception\EntityTypeRegistrationCollisionException` instead of a generic duplicate-registration error.

### What changed

- Same-class duplicate registration now fails with `[ENTITY_TYPE_DUPLICATE]`.
- Shadow registration of a framework-owned canonical type now fails with `[ENTITY_TYPE_SHADOW_COLLISION]`.
- The rendered message names the entity type id, the already-registered provider class, the canonical entity class, the incoming provider class, and the conflicting entity class.
- Bundle-scoped writes now emit `[MISSING_BUNDLE_SUBTABLE]` through `LoggerInterface::notice()` when bundle-field values are present but the matching `{base}__{bundle}` subtable does not exist at save time.

### How to read the collision wording

- `[ENTITY_TYPE_DUPLICATE]` means the same entity type id was registered twice with the same class. Drop the duplicate registration; this is stale provider wiring, not an extension point.
- `[ENTITY_TYPE_SHADOW_COLLISION]` means a consumer tried to register an entity type id that the framework already owns, but with a different class. Drop the shadow registration and migrate callers to the canonical framework type.

### If you were shadowing `group` or `group_type`

Remove the duplicate `entityType()` registration from your consumer provider instead of trying to override the framework-owned id. The canonical owners are `Waaseyaa\Groups\GroupsServiceProvider`, `Waaseyaa\Groups\Group`, and `Waaseyaa\Groups\GroupType`.

If your app still has shadow classes or imports that assume consumer-owned group types, use the reconciliation ADR as the migration path:

- [`docs/superpowers/specs/2026-04-19-groups-reconciliation-adr.md`](docs/superpowers/specs/2026-04-19-groups-reconciliation-adr.md)

That ADR is the concrete path for the Minoo-shaped cleanup. Minoo `main` no longer carries live duplicate `group` / `group_type` registration in `AppServiceProvider`; the remaining migration case is shadow-class residue and call sites that still import those shadows. Later arc phases handle the `HasCommunityInterface` and `GroupType` key reconciliation that make those shadows removable.

### If you see `[MISSING_BUNDLE_SUBTABLE]`

Your app has registered bundle-scoped fields for a bundle whose storage subtable has not been materialized yet. The save path will keep the base-row write, but the bundle-field values for that write will not persist. Ship or run the schema migration / sync that creates the missing `{base}__{bundle}` subtable before saving that bundle in production.
