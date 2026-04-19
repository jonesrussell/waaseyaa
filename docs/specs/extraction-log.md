# Extraction Log

Tracks code extracted from app repos (minoo, claudriel) into the waaseyaa framework.

See `waaseyaa:framework-extraction` skill for the extraction process.

---

## 2026-04 — SlugGenerator (#692)

| | |
|---|---|
| **Source** | Minoo `Minoo\Support\SlugGenerator` (historical; removed from app tree) |
| **Package** | `waaseyaa/foundation` |
| **Class** | `Waaseyaa\Foundation\SlugGenerator` — `generate(string $value): string` |
| **Tests** | `packages/foundation/tests/Unit/SlugGeneratorTest.php` |
| **Consumers** | Minoo ingestion mappers (`NcArticleToEventMapper`, `DictionaryEntryMapper`, etc.) via `use Waaseyaa\Foundation\SlugGenerator` |

## 2026-04 — GeoDistance (#693)

| | |
|---|---|
| **Source** | Minoo `Minoo\Support\GeoDistance` (historical; removed from app tree) |
| **Package** | `waaseyaa/geo` (dedicated package; not merged into foundation to keep geospatial helpers optional) |
| **Class** | `Waaseyaa\Geo\GeoDistance` — `haversine(float $lat1, float $lon1, float $lat2, float $lon2): float` (kilometres) |
| **Tests** | `packages/geo/tests/Unit/GeoDistanceTest.php` |
| **Consumers** | Minoo (`waaseyaa/geo` in `composer.json`): `CommunityController`, `FeedController`, `FeedAssembler`, geo domain services, etc. |

## 2026-04 — Mail API consolidation (#798, tracker #1157)

| | |
|---|---|
| **Change** | Removed parallel `MailDriverInterface` / `MailMessage` / `SendGridDriver` stack. |
| **Package** | `waaseyaa/mail` |
| **API** | `MailerInterface::send(Envelope)` only; `MailServiceProvider` binds `TransportInterface` → `SendGridTransport` when `mail.sendgrid_api_key` and `mail.from_address` are set (after trim), else `array` or `LocalTransport` per `mail.transport`. |
| **Framework consumers** | `AuthMailer` (injected `MailerInterface` + `authEmailConfigured` flag; `UserServiceProvider`), `MailChannel` / notifications (unchanged). |
| **App follow-up** | Minoo dropped duplicate `SendGridDriver` registration; `MailTestCommand`, `MessageDigestCommand` use `MailerInterface` / `Envelope`. |

## 2026-04 — Flash in SSR (#697, tracker #1157)

| | |
|---|---|
| **Status** | No new package: `Waaseyaa\SSR\Flash\Flash`, `FlashMessageService`, `FlashTwigExtension` already live under `packages/ssr`; tracker #1157 mail work does not relocate flash. |

## 2026-04 — waaseyaa/groups package extraction

| | |
|---|---|
| **Source** | Multi-bundle Group concept, previously an open design question in Minoo. No pre-existing app code relocated — extracted as a framework-level content-type package alongside the bundle-scoped storage work. |
| **Package** | `waaseyaa/groups` (layer 2 content-type) |
| **Classes** | `Waaseyaa\Groups\Group` (extends `ContentEntityBase`; id=gid, uuid, bundle=type, label=name), `Waaseyaa\Groups\GroupType` (extends `ConfigEntityBase`), `Waaseyaa\Groups\GroupsServiceProvider` |
| **Tests** | `packages/groups/tests/Unit/GroupsServiceProviderTest.php`, `packages/groups/tests/Integration/StandaloneConsumptionTest.php`, `packages/groups/tests/Integration/TwoBundleCoexistenceTest.php` |
| **Specs** | `docs/specs/bundle-scoped-storage.md`, `docs/specs/bundle-scoped-fields.md`, `docs/specs/entity-system.md` |
| **Consumers** | None in-framework; product code (Minoo) registers GroupType config entities and bundle-scoped fields via `EntityTypeManager::addBundleFields()`. Ships with zero pre-registered bundles. |

## Future adoption candidates

Per-bundle field storage (see `docs/specs/bundle-scoped-storage.md`, `docs/specs/bundle-scoped-fields.md`) was introduced for `waaseyaa/groups`. The same pattern is a candidate — not a proposal — for several existing content-type packages where bundles today share a flat table and bundle-specific fields collide in practice. No migration in this PR; no timeline.

- **node** (`packages/node`). Node bundles (`article`, `page`, etc.) currently store all fields on the base `node` table. Bundle-specific fields collide on a flat schema. Adoption candidate: per-bundle subtables (`node__article`, `node__page`, …) following the bundle-scoped-storage/bundle-scoped-fields contract. Deferred. (Spec: `docs/specs/entity-system.md`.)
- **taxonomy** (`packages/taxonomy`). Term storage is per-vocabulary in concept but per-entity-type in practice — vocabulary-specific fields sit on the base term table. Adoption candidate: per-vocabulary subtables following the same pattern. Deferred.
- **media** (`packages/media`). Media bundles (image, document, video, …) diverge on bundle-specific metadata. Adoption candidate: per-bundle subtables. Deferred.

## Follow-ups

- **Shared FieldDefinition → column mapper.** `SqlSchemaHandler::deriveColumnSpec()` (introduced with the `waaseyaa/groups` extraction's bundle-subtable work) translates `FieldDefinition::getType()` to a Waaseyaa column spec locally. It should be promoted to a shared mapper once a second consumer needs it — the likely trigger is a `NodeServiceProvider`-style migration from hand-authored column-spec arrays to `FieldDefinition` objects. Until then, keep it private to `SqlSchemaHandler`; premature extraction without a second caller would bake the current limited type set (`string/text/integer/boolean/float`) into a shared contract. (Spec: `docs/specs/bundle-scoped-storage.md`.)
- **`FieldDefinitionInterface` is not yet public cross-package API.** Registered `FieldDefinition` objects are treated as opaque by everything outside `waaseyaa/field` + `waaseyaa/entity-storage` — `FieldDefinitionRegistry` returns them but no downstream package (admin, graphql, api) yet inspects their metadata. Revisit the interface surface (stability guarantees, accessors, typed metadata shape) when a package outside `waaseyaa/groups` needs to read registered field definitions. Premature public-API stabilization would lock in the minimal shape required for the bundle-subtable path. (Specs: `docs/specs/entity-system.md`, `docs/specs/bundle-scoped-fields.md`.)
- **Per-bundle upsert implemented as SELECT-then-INSERT-or-UPDATE.** `SqlEntityStorage::persistBundleRow()` (commit 4) performs a PK existence check and then routes to `INSERT` or `UPDATE` because DBAL does not expose a portable upsert (MySQL `INSERT … ON DUPLICATE KEY UPDATE`, PostgreSQL `INSERT … ON CONFLICT`, SQLite `INSERT … ON CONFLICT`, MSSQL `MERGE` all differ). Acceptable for current load. Revisit with dialect-specific paths — branching on `Connection::getDatabasePlatform()` — if subtable writes become a hotspot under concurrent workloads (double round-trip + race window between SELECT and INSERT/UPDATE). (Spec: `docs/specs/bundle-scoped-storage.md`.)
- **Boot-time FK enforcement health check.** Delivered in commit 7 (`operator-diagnostics`) — SQLite requires `PRAGMA foreign_keys = ON` issued via `Connection::executeStatement()`; MySQL/InnoDB is on by default but can be disabled per-session. Bundle-subtable `ON DELETE CASCADE` silently becomes a no-op if FKs are off. Retained here as a pointer: any new driver added to `DBALDatabase` must be audited for FK-default behaviour. (Spec: `docs/specs/operator-diagnostics.md`.)
- **PK-collision failure mode on bundle upsert race.** Extends the SELECT-then-INSERT-or-UPDATE bullet above. Under concurrent writes the loser of the race sees a PK constraint violation rather than a clean UPDATE — the subtable PK prevents duplicate rows but surfaces the race as an exception at the caller. Most writes are inside `$this->database->transaction()` so the window is small, but when the shared upsert helper lands (dialect-specific `ON CONFLICT` / `ON DUPLICATE KEY UPDATE` / `MERGE`) document the invariant that current callers must be prepared to retry on constraint violations. (Spec: `docs/specs/bundle-scoped-storage.md`.)
- **`deriveColumnSpec()` first-extension trigger.** Companion to the shared-mapper bullet above. The type map (`string/text/integer/boolean/float`) is deliberately minimal for v1 bundle fields. The first external consumer needing `datetime`, `json`, or an enumerated type will force the extension — whether local (expand the private method) or shared (promote to the shared mapper). Capture that moment as the v1 breakpoint; adding a type without an actual consumer bakes speculation into the contract. (Spec: `docs/specs/bundle-scoped-storage.md`.)
- **`HealthChecker` constructor parameter creep.** Six positional params after #1297, with one nullable (`?FieldDefinitionRegistryInterface`) at the end. Not a refactor target on its own — the nullable tail is load-bearing for backward compatibility with consumers built before per-bundle field diagnostics landed. Revisit with a builder or container-resolved factory the moment a seventh dependency arrives; until then, new nullables go at the end to preserve the existing positional order. (Spec: `docs/specs/operator-diagnostics.md`.)
