# Sub-task 3 — Spec update plan

**Goal.** Bless K1–K7 + C1 ratifications (per `spec.md` 2026-04-30) into the canonical specs `docs/specs/entity-system.md` and `docs/specs/bundle-scoped-storage.md`. No new architectural decisions — text migration of already-ratified conventions/contracts.

## Issue → K-grade → WP map (used for cross-references)

| Issue | K-grade | WP | One-liner |
|---|---|---|---|
| #1298 | K1 | WP03 | Single helper for `{base}__{bundle}` + structural `__`-in-bundle-id guard at `EntityTypeManager::addBundleFields()` |
| #1308 | K2 | WP04 | `SqlEntityQuery::routeFields()` consults registry hint; matches write-side `getDataStoredCoreFieldNames()` |
| #1257 | K3 | WP05 | `SqlEntityQuery::condition()` coerces `_data` JSON value comparisons by declared field cast |
| #1299 | K4 | WP06 | `mergeBundleSubtableRow*()` logs once per `(entity_type, bundle)` when subtable missing; no throw |
| #1313 part A | K4 | WP07 | `addBundleFields()` notice when subtable absent at registration |
| #1313 part B | K7 | (no-op per D2) | `EntityTypeRegistrationCollisionException::duplicate` already names both registrants |
| #1300 | K6 | WP08 | HealthChecker layer placement option (c) — codified kernel-adjacent exemption |
| #1301 | K5 | WP09 | `HealthChecker::findOrphanSubtables()` uses DBAL `AbstractSchemaManager::listTableNames()` (sqlite_master fast-path retained) |
| #1304 | C1 | WP10 | Tenancy opt-in moves from `HasCommunityInterface` marker to declarative `tenancy:` key on `EntityType` |

---

## Edits to `docs/specs/entity-system.md` (1438 lines today)

### Edit 3.A — Spec-reviewed line at top

Add a new spec-reviewed line dated 2026-04-30:

```
<!-- Spec reviewed 2026-04-30 — mission #1257 WP02 ratification: K1–K7 conventions + C1 tenancy contract; HasCommunityInterface deprecated; see ./bundle-scoped-storage.md for K1/K4/K5 details -->
```

### Edit 3.B — `## EntityType Definition` (line 456)

Extend the constructor example with the new `tenancy:` parameter:

```php
new EntityType(
    id: 'group',
    label: 'Group',
    class: \Waaseyaa\Groups\Group::class,
    keys: ['id' => 'gid', 'uuid' => 'uuid', 'label' => 'name', 'bundle' => 'type'],
    bundleEntityType: 'group_type',
    tenancy: ['scope' => 'community'],   // NEW (C1, mission #1257)
    fieldDefinitions: [],
);
```

Add a new bullet to the "New parameters" list:

> - `tenancy: ?array` — opt-in declarative tenancy scope; `['scope' => 'community']` enables `CommunityScope` wiring without requiring the entity class to implement `HasCommunityInterface`. `null` (default) means non-tenant. Lives next to entity-keys / bundle-entity-type. See **Community Scoping** below for wiring + deprecation cycle.

### Edit 3.C — `### EntityTypeManagerInterface` (line 343)

Append a paragraph stating K7 + K1 + K4-part-A:

> **Duplicate registration (K7).** `EntityTypeRegistrationCollisionException::duplicate(...)` (raised by the manager when a type id is registered twice) names **both** registrants — the existing one (FQCN of class + provenance) and the incoming one — so the operator can reach both call sites from one exception body. Convention only; no contract change.
>
> **Bundle-id structural guard (K1).** `EntityTypeManager::addBundleFields($entityTypeId, $bundleId, $fieldDefinitions)` rejects bundle ids containing `__` at registration time. The double-underscore is reserved for the `{base}__{bundle}` subtable naming; structural validation here prevents downstream collisions in `SqlSchemaHandler` / `SqlEntityStorage` / `SqlEntityQuery`.
>
> **Bundle-subtable absence notice (K4 part A).** When `addBundleFields()` registers fields for a `(type, bundle)` whose subtable is not yet materialized in storage, the manager emits `LoggerInterface::notice()` with diagnostic code `MISSING_BUNDLE_SUBTABLE` once per `(entity_type, bundle)` and continues — registration is pre-materialization and runtime DDL is forbidden. See [`bundle-scoped-storage.md`](./bundle-scoped-storage.md) §Lifecycle.

### Edit 3.D — `## Casting & hydration architecture` (line 79+)

Append a subsection at the end of the existing ST-9 section:

> ### Query-builder boundary (K3, mission #1257)
>
> `SqlEntityQuery::condition()` extends ST-9 to the read path: when comparing a value stored in `_data` (JSON-extracted), it inspects the field's declared `FieldDefinition` cast and either binds the parameter accordingly or wraps `json_extract(...)` in `CAST(... AS TEXT)` so string-vs-integer comparisons commute against SQLite's loose JSON typing. Same source-of-truth as ST-9 (the cast registry) — the query builder is just a second consumer.
>
> Reproduction case: integer `_data` field compared to a string literal. Pre-K3, the SQLite path returned an empty set silently. Post-K3, the comparison commutes against the declared cast.

### Edit 3.E — `### SqlEntityQuery` substrate paragraph (around line 596–615)

Append a paragraph:

> **Read–write routing parity (K2, mission #1257).** `SqlEntityQuery::routeFields()` consults the `FieldDefinitionRegistry::isDataStored()` hint (the same source `SqlEntityStorage::splitForStorage()` uses on write via `getDataStoredCoreFieldNames()`). The registry hint wins over `SchemaInterface::fieldExists()` on the query side, mirroring the write side. No silent dual-source: if a field has been promoted to `FieldStorage::Data` while a legacy column lingers in the table, both sides agree the canonical home is `_data` and reads come from there.

### Edit 3.F — `### Community Scoping (Multi-tenancy)` (line 751)

This section is the largest change. Restructure as follows:

1. **Replace** the opening paragraph "Service providers check `is_a($entityType->getClass(), HasCommunityInterface::class, true)` and inject `CommunityScope` only for community-aware entity types" (line 775) with:

   > Service providers consult `EntityType::getTenancy()` and inject `CommunityScope` when the declared scope is `'community'`. Config entities and system entities receive no `CommunityScope`.

2. **Insert** a new subsection after "HasCommunityInterface / HasCommunityTrait":

   > #### Tenancy declaration (C1, mission #1257 WP10)
   >
   > **Status: canonical.** Entities opt into community scoping via `EntityType` registration:
   >
   > ```php
   > new EntityType(
   >     id: 'group',
   >     class: \Waaseyaa\Groups\Group::class,
   >     tenancy: ['scope' => 'community'],
   >     // ...
   > );
   > ```
   >
   > `EntityType::getTenancy(): ?array` returns the declared shape (or `null` for non-tenant types). `SqlStorageDriver` and `MemoryStorageDriver` both wire `CommunityScope` based on this declaration; the entity class needs no marker interface.
   >
   > **Why declarative.** Framework-shipped `final` entity classes (e.g. `Waaseyaa\Groups\Group`) cannot be marked by consumers via interface — exactly the class-hierarchy coupling that bundle-scoped fields exist to avoid. Tenancy is a **registration-site** concern, not a class-hierarchy concern.

3. **Insert** a new subsection after the C1 declaration:

   > #### Migration: `HasCommunityInterface` → declarative tenancy (mission #1257 WP10)
   >
   > **Status: deprecation cycle active. Removal in next minor release.**
   >
   > See `packages/groups/CHANGELOG.md` for the operator-facing migration recipe. In summary:
   >
   > | Before | After |
   > |---|---|
   > | `class MyEntity extends ContentEntityBase implements HasCommunityInterface { use HasCommunityTrait; }` | `class MyEntity extends ContentEntityBase { /* no marker */ }` |
   > | Service provider: `is_a($entityType->getClass(), HasCommunityInterface::class, true)` | `EntityType` registration: `tenancy: ['scope' => 'community']` |
   >
   > During the deprecation cycle, `HasCommunityInterface` continues to function — wiring still injects `CommunityScope` for entities that implement it. On first wiring per `(entity-type id)` per process, `LoggerInterface::warning()` emits a one-time deprecation notice naming the entity type and pointing to the migration recipe. Removal scheduled for the next minor release.
   >
   > **Note for adopters mid-migration (e.g. Minoo).** Consumers running their own `App\Entity\Group` plus a composer dep on `waaseyaa/groups` should: (1) adopt `tenancy:` on their EntityType registration first; (2) verify cross-tenant isolation tests still pass; (3) only then collapse the local `App\Entity\Group` onto `Waaseyaa\Groups\Group`. Order matters because the `tenancy:` flip is wiring-local while the class collapse touches every call site.

---

## Edits to `docs/specs/bundle-scoped-storage.md` (117 lines today)

### Edit 3.G — `## Naming` (line 15)

Append a paragraph after "Bundle identifiers must not contain `__`":

> The naming rule and the `__`-in-bundle-id guard are codified in a single helper consumed by `SqlSchemaHandler`, `SqlEntityStorage`, and `SqlEntityQuery`; see [`entity-system.md` §EntityTypeManagerInterface](./entity-system.md) for the registration-time guard at `EntityTypeManager::addBundleFields()`. (Mission #1257 K1.)

### Edit 3.H — `## Lifecycle` § "Runtime notice on save-path mismatch" (around line 92)

Strengthen the notice paragraph to explicitly state K4:

> **Runtime notice on save-path mismatch (K4, mission #1257).** `SqlEntityStorage::save()` is the first deterministic runtime hook that can truthfully tell whether bundle-scoped values are about to be dropped because a required `{base_table}__{bundle}` subtable is still missing. When bundle values are present, the entity has a concrete bundle, and the subtable does not exist, storage emits a `LoggerInterface::warning()` with diagnostic code `MISSING_BUNDLE_SUBTABLE` **once per `(entity_type, bundle)` per process** (memoized on the bundle-subtable cache) and continues the base-row write without the bundle-field values. **No throw** — preserves the open-by-default, diagnostic-driven model from `operator-diagnostics.md`. The same once-per-pair memoization applies to `mergeBundleSubtableRow()` / `mergeBundleSubtableRowsBatch()` on the load path.

### Edit 3.I — `## Drift diagnostic` (line 95)

Append a paragraph at the end of the section:

> **Dialect portability (K5, mission #1257).** `HealthChecker::findOrphanSubtables()` enumerates tables via DBAL `AbstractSchemaManager::listTableNames()` filtered against `{base}__%`, with SQLite's `sqlite_master` retained as a fast-path. Test matrix gates a non-SQLite run behind a docker-compose env var so MySQL/PostgreSQL coverage is mechanical. See `operator-diagnostics.md` for the full algorithm.
>
> **Layer placement (K6, mission #1257).** `HealthChecker` lives in `packages/foundation/src/Diagnostic/` and imports from L1 (`Waaseyaa\Entity\*`, `Waaseyaa\EntityStorage\SqlSchemaHandler`, etc.) — kernel-adjacent because it is wired only from `ConsoleKernel`. The cross-layer exemption is codified in `bin/check-package-layers` `KERNEL_EXEMPT_FILES` per mission #824 WP02 surface C; entry rationale cites K6(c). See [`infrastructure.md` §Kernel exemption surface](./infrastructure.md).

### Edit 3.J — `## References` (line 112)

Add two cross-references:

```
- Tenancy declaration on `EntityType`: [`entity-system.md` §Community Scoping](./entity-system.md).
- Mission ratification: `.kittify/missions/1257-entity-storage-hardening/spec.md`.
```

---

## Out of scope for sub-task 3

- **Code changes** — sub-task 3 is text only. Implementation of K1–K7 + C1 happens in WP03–WP10.
- **CHANGELOG migration recipe** — that's sub-task 5, lands in `packages/groups/CHANGELOG.md`.
- **Issue closures** — sub-task 2.

## Total impact estimate

- `entity-system.md`: ~80–100 added lines across 6 sections; 3 lines replaced (the `is_a(..., HasCommunityInterface::class, true)` paragraph).
- `bundle-scoped-storage.md`: ~25–30 added lines across 4 sections; one paragraph strengthened.
- 1 spec-reviewed line at the top of `entity-system.md`.

## Verification gate

After edits, run:
```
bin/check-package-layers
composer cs-check
composer phpstan
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite Integration
```

No code changes → all gates expected green by inspection (markdown-only diff).
