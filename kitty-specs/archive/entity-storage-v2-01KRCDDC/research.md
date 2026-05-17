# Research — Entity Storage v2 (Multi-Backend Field Storage with Revisions)

Mission: `entity-storage-v2-01KRCDDC` (M-001)
Canonical spec: `docs/specs/entity-storage-v2.md`
Mission metadata: `docs/specs/missions/M-001-entity-storage-v2/mission.json`
Governing ADRs: 010 (multi-backend field storage), 011 (entity lifecycle events), 016 (revisions first-class).
Charter governance: §5.3 (governs), §3.2.7 and §3.2.8 (contributes-to-beta-gate).

This document captures the decisions, rationale, and evidence that frame WP planning. It is a research artifact — design choices below are derived from the ratified spec and ADRs; nothing here amends them.

---

## 1. Mission framing

Waaseyaa's current entity persistence is single-backend: every field is JSON-encoded into a `_data` TEXT column on the entity row (`SqlEntityStorage`/`SqlSchemaHandler` in `packages/entity-storage/src/`). That works for prototyping but blocks four substrate capabilities the charter calls out:

1. **Real SQL query semantics** for fields (filter/sort by indexed columns, see ADR 010).
2. **Per-field storage strategy** (heterogeneous backends per entity type, e.g. text in columns + embeddings in a vector store).
3. **Lifecycle event dispatch** at well-defined points around storage operations (ADR 011).
4. **First-class revisions** with a canonical revision table shape (ADR 016).

This mission delivers all four by introducing a `FieldStorageBackendInterface`, a storage coordinator that fans operations across backends, two concrete backends (`sql-blob` — refactor of the current path; `sql-column` — new), a revision substrate co-designed with `sql-column`, a per-revision access operation, and a migration generator. It validates the substrate by migrating one Minoo entity type (`teaching`) end-to-end.

---

## 2. Decisions (all anchored to the ratified spec)

### D1. Backend contract is the central stable surface

A single `FieldStorageBackendInterface` (`packages/entity-storage/src/Backend/`) governs every field-storage strategy. Backends are discovered through a provider capability marker (`HasFieldStorageBackendsInterface`) — Composer-based discovery, no service-locator lookup, no class-string registries.

**Rationale:** The audit (2026-05-11) called out that the current `_data` column is the single hidden assumption blocking ADRs 010/011/015/016. Lifting that assumption into a contract makes future backends (vector, remote) additive instead of breaking.

**Reference:** spec §3.1, §5; ADR 010.

### D2. Reserved backend-id namespace

`sql-blob`, `sql-column`, and `vector` are reserved. `vector` is reserved but not implemented here. Third-party ids must not collide; a `BackendIdCollisionException` raises at registration.

**Rationale:** Reserving identifiers up front prevents the ecosystem fragmenting on naming. The framework owns the canonical three; everything else is namespaced.

**Reference:** spec §5.1, §5.2.

### D3. `sql-blob` is a refactor with zero observable behavior change

The existing `_data` JSON path becomes the `sql-blob` backend. WP03 includes a behavior-identity test suite: snapshot of CRUD behavior before refactor must equal snapshot after.

**Rationale:** All currently-shipping entity types persist via `_data`. Any deviation from the existing semantics would be a breaking change at the alpha-stability surface. Behavior identity is the gate.

**Reference:** spec §3.2, §7, §12.4.

### D4. `sql-column` is new and indexable

A new backend that materializes each field as a real SQL column with the type mapping in §8.2. Indexes are declared via `FieldDefinition::indexed()`. Query support is exposed via `supportsQuery()`; backends that cannot satisfy a definition's index/query needs throw `UnsupportedQueryException` at **definition validation time**, not at query time.

**Rationale:** Failing at definition time means definers see breakage in CI/dev, not end users at runtime.

**Reference:** spec §3.3, §8, §6 (coordinator), §3.7 (error model).

### D5. The coordinator owns lifecycle events

`BeforeSaveEvent`, `AfterSaveEvent`, `BeforeDeleteEvent`, `AfterDeleteEvent` are dispatched by the coordinator, not by backends. Backends only know how to read/write/query a field; observability and policy hooks live one layer up.

**Rationale:** Decouples lifecycle semantics from backend implementations. Adds-of-backends do not multiply event surfaces.

**Reference:** spec §3.5, §6; ADR 011.

### D6. Partial-save semantics are explicit

Coordinator fan-out is per-field. If one backend fails mid-write, the coordinator raises `PartialSaveException` (with backend id + field id + cause) rather than attempting silent rollback. Callers decide the recovery policy.

**Rationale:** True cross-backend atomicity is not achievable for arbitrary backends (vector stores, remote services). Explicit failure is honest; silent retry is dangerous.

**Reference:** spec §3.9, §6.5.

### D7. Revisions are an entity-type opt-in, not a global change

A new `EntityType::$revisionable: true` flag plus `entityKeys.revision` slot turns on revisions per entity type. `RevisionableEntityInterface` + `RevisionableEntityStorageInterface` define the surface. Existing entity types are untouched.

**Rationale:** Forcing revisions universally would be an alpha-breaking change and would generate unneeded write amplification for entity types that do not need history.

**Reference:** spec §3.6, §9; ADR 016.

### D8. Revision tables are co-designed with `sql-column`

The canonical revision shape (§9.1) is column-based and lives alongside the entity's current-revision row. `sql-blob` may opt into revisions too (existing entity types can adopt revisions without migrating to columns first) — its revision table mirrors the `_data` JSON shape.

**Rationale:** Co-design with `sql-column` is where the substrate is going; preserving `sql-blob` revisions keeps the opt-in path open for entity types not yet ready to migrate.

**Reference:** spec §9, §7.3.

### D9. Per-revision access uses a new `view_revision` op

Access policies for viewing historical revisions are a distinct operation from viewing the current entity. `GateInterface` adds `view_revision`. A fallback rule applies the entity-level `view` policy when no `view_revision` policy is declared.

**Rationale:** Some apps want stricter rules for historical revisions (e.g. only editors can see old drafts). Keeping the op distinct gives policies room to differentiate without rewriting `view`.

**Reference:** spec §3.7, §11; ADR 016.

### D10. Migration generator emits standard waaseyaa migrations

`bin/waaseyaa make:storage-migration <entity_type>` generates a migration that runs through the existing `bin/waaseyaa migrate` system (ADR 009 manifest). No parallel runner is introduced.

**Rationale:** One migration system. Storage migrations are not special — they are a category of schema migration.

**Reference:** spec §10; open question §16.2 (resolved: ride existing system); ADR 009.

### D11. Validation entity type is `teaching`

WP11 migrates one Minoo entity type end-to-end to prove the substrate. `teaching` is the canonical pick: ~50 fields across the type-mapping table, real query patterns, editorial revision use case, migration completes in well under 60 seconds. Alternates: `event`, `cultural_collection`. Engagement entities are explicitly NOT the first validation type (volume too high for a first migration).

**Rationale:** Pick the smallest-and-richest target that exercises the most surface. Defer high-volume migrations until the pattern is proven.

**Reference:** spec §15; `mission.json.validation_entity_type: "teaching"`.

---

## 3. Open questions — recommended resolutions

These mirror spec §16. None block WP01; all should be resolved before WP10/WP11.

| # | Question | Recommendation | Resolve by |
|---|---|---|---|
| Q1 | Backend-registration order across packages | Composer `installed.json` install order, with optional `priority: int` override on the provider capability. | WP01 |
| Q2 | Storage-migration runner | Ride existing `bin/waaseyaa migrate` system (ADR 009 manifest). | WP10 |
| Q3 | Reversibility limit on large migrations | Keep reversibility as default. Add `expectedReverseSeconds` docblock annotation that emits a warning at apply time when above a threshold. | WP10 |
| Q4 | `sql-column` → `sql-column` schema evolution | Coordinate with mission #529 (`schema-evolution-v2.md`); cite its manifest as substrate. | WP10 |
| Q5 | `RevisionPruner` scope | Ship the class disabled per ADR 016; first-app pruning policies are out of scope. | WP07/WP08 |
| Q6 | `SaveContext` shape | Introduce a dedicated `SaveContext` value object (not a flags array on `save()`). Extensible to future flags (`withoutEvents()`, etc.). | WP04 |

---

## 4. Risk register

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| `sql-blob` refactor introduces silent behavior drift | Medium | High | WP03 behavior-identity test suite is a hard gate; snapshot every observable behavior of current `SqlEntityStorage` before refactor begins. |
| Coordinator complexity grows beyond charter §4 cognitive budget | Medium | Medium | Keep fan-out logic to a single class (`EntityStorageCoordinator`); push backend-specific behavior into backend classes. Adopt rule: coordinator never reads field-level data, only routes. |
| Partial-save failures cause inconsistent on-disk state in practice | Low | High | `PartialSaveException` carries full context; document recovery patterns in `entity-system.md` update. WP11 exercises the failure mode in integration tests. |
| Revision write amplification on `sql-blob` opt-ins | Medium | Low | Revision tables are explicit opt-in; entity types that do not need history pay nothing. |
| Migration generator emits unsafe migrations for large entity types | Low | High | FR-043 reversibility + `expectedReverseSeconds` warning. WP11 validates with `teaching` (small dataset) first. |
| `view_revision` policy fallback is surprising | Low | Medium | Document the fallback rule in `entity-system.md` and `access-control.md`; emit a structured log line when fallback fires. |
| `vector` backend is reserved but not implemented — third parties may attempt early | Low | Low | `BackendIdCollisionException` at registration; the reserved-ids constant is on the stable surface. |

---

## 5. Sequencing summary

Twelve work packages. Critical path:

```
WP01 (contract)
  → WP02 (coordinator skeleton)
     → WP03 (sql-blob refactor)         ─┐
     → WP04 (events + PartialSave)       │  parallelizable after WP02
     → WP05 (sql-column)                ─┘
        → WP06 (query support)
        → WP07 (revision schema)
           → WP08 (revision storage)
              → WP09 (view_revision)    ─┐ parallelizable after deps met
              → WP10 (migration CLI)    ─┘
                 → WP11 (Minoo validation)
                    → WP12 (conformance suite + docs, closing)
```

Parallel windows: WP04 ‖ WP05 after WP02; WP06 ‖ WP07 after WP05; WP09 ‖ WP10 after their deps.

Validation gate is WP11. The mission is not complete until `teaching` ships in production for 7 days without incident (acceptance criterion 4) and charter §3.2 criterion 8 ("revisions in production") is satisfiable.

---

## 6. Downstream consumers

This mission unblocks:

- **M-002 WP05** — migration platform v1 leans on the same migration manifest path.
- **M-004** — translatable revisions builds directly on the revision substrate.

Per `mission.json.downstream_unblocks`. Validation consumer is `minoo` (charter-listed downstream app).

---

## 7. Out-of-scope, restated

Items explicitly deferred (spec §1.2):

- Content moderation workflows.
- Per-field translation (charter §11 Q7).
- Revision admin UI.
- Vector backend implementation.
- Remote / external-entity backend.
- Cross-backend query coordination (charter §11 Q8).
- Auto-pruning of old revisions.
- Listing-builder admin UI (ADR 015).
- Migrating all Minoo entity types beyond `teaching`.

Reviewers must reject any WP-level work that bleeds into these areas.

---

## 8. Acceptance restatement

Per spec §14, the mission is done when:

1. All 12 WPs merged.
2. All §3 FRs covered by tests.
3. Backend-conformance suite green for `sql-blob` and `sql-column`.
4. WP11 `teaching` migration in production, 7 days without related incident.
5. Charter §3.2 criterion 8 (revisions in production) satisfiable.
6. Charter §5.3 stable-surface entries reflected in `public-surface-map.md` and `public-surface-map.php` at tier `stable`, status `present`.
7. First concrete upgrade guide at `docs/upgrades/waaseyaa-alpha-<X>-to-<Y>.md` per FR-056.

---

## 9. Evidence trail

See:
- `kitty-specs/entity-storage-v2-01KRCDDC/research/source-register.csv` — every source consulted.
- `kitty-specs/entity-storage-v2-01KRCDDC/research/evidence-log.csv` — each finding tied to a source.
- `kitty-specs/entity-storage-v2-01KRCDDC/data-model.md` — concrete interfaces, classes, and storage shape.
