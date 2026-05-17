# Implementation Plan: Entity Storage — Translatable + Revisionable Two-Axis Interaction

**Branch:** `main` (planning-and-merge target)
**Date:** 2026-05-16
**Spec:** [`spec.md`](spec.md) (revalidated 2026-05-17 against M-006 + M-007 substrates)
**Doctrine spec:** [`docs/specs/entity-storage-translatable-revisions.md`](../../docs/specs/entity-storage-translatable-revisions.md)
**Mission ID:** M-004 (display) / `01KRCDEE` (Spec Kitty slug suffix)
**Slug:** `entity-storage-translatable-revisions-01KRCDEE`

**Branch contract:**
- `current_branch`: `main`
- `planning_base_branch`: `main`
- `merge_target_branch`: `main`
- `branch_matches_target`: `true` ✓

## Summary

Ship the two-axis (revisions × translation) substrate per [ADR 017](../../docs/adr/017-per-field-translation.md) §"Revision × translation interaction." Compose M-006's translation surface (`TranslatableInterface`, `TranslatableEntityTrait`, `FieldDefinition::translatable()`, `TranslationSchemaHandler`, `SaveContext::withLangcode()`, `EntityTranslationException`, `AfterSaveEvent::affectedLangcodes()`, `AddTranslationsMigrationGenerator`) with the single-axis revisions substrate (`RevisionableEntityInterface`, `RevisionableEntityTrait`, `RevisionableStorageDriver`, `RevisionTableBuilder`) into a per-`(entity_id, langcode, vid)` composite-key revision shape — non-translatable fields stored **once** on the default-langcode revision and referenced by other-langcode revisions; translatable fields snapshotted per-`(langcode, vid)`. Save semantics: per-translation revision creation in isolation; multi-language atomic saves via new `SaveContext::withTranslations(array)` builder. Load semantics: `$entity->getTranslation('fr')->loadRevision(7)` composes M-006 + revisions cleanly. Access: existing `view_revision` / `translate` operations apply to the translation instance; policies introspect `activeLangcode()`. Migration generator: extend `AddTranslationsMigrationGenerator` for two-axis promotion + ship new `--add-revisions` flag. Listing pipeline: verify M-007's `Filter::langcode()` + `ListingCacheInvalidator` route through per-`(entity, langcode)` current-revision pointer (FR-033a). Exception surface consolidates per M-006 pattern: extend `EntityTranslationException` (new factory `historicalRevisionWrite`) + ship one new `StorageMigrationException` (factories: `noOpPromotion`, `unsupportedTwoAxisField`). 49 FRs, 8 WPs. Validation: Minoo `teaching` end-to-end (5 revisions across 2 languages, independent sequencing).

## Technical Context

| Field | Value |
|---|---|
| **Language / Version** | PHP 8.5+ (project minimum; `declare(strict_types=1)` mandatory) |
| **Primary Dependencies** | Symfony 7.x (EventDispatcher, Validator, Uid), Doctrine DBAL 4.x (sql-blob + sql-column backends), `waaseyaa/foundation` (LoggerInterface, EventDispatcher, AfterSaveEvent w/ `affectedLangcodes`), `waaseyaa/entity` (M-006 `TranslatableInterface` + `RevisionableEntityInterface` + `EntityTranslationException`), `waaseyaa/entity-storage` (M-006 `TranslationSchemaHandler` + `SaveContext` + `RevisionTableBuilder` + `RevisionableStorageDriver` + `RevisionableSqlBlobStorage`), `waaseyaa/listing` (M-007 `Filter::langcode()` + `ListingCacheInvalidator`), `waaseyaa/access` (GateInterface), `waaseyaa/cli` (M-006 `AddTranslationsMigrationGenerator`). |
| **Storage** | SQLite + MySQL + PostgreSQL via DBAL. New schema shape: composite-PK revision table for sql-column backend (`<table>__translation__revision` keyed `(vid)` with composite uniqueness on `(tid, langcode, vid)`); composite-PK per-revision-row table for sql-blob backend (single table `<table>__translation__revision` with `_data` blob per `(tid, langcode, vid)`). Existing single-axis revision tables (surrogate `vid` PK) remain unchanged for revisionable-only types — backward compatible. |
| **Testing** | PHPUnit 10.5 (no `-v` flag). Contract tests with `#[CoversNothing]`, abstract base + per-backend concrete subclasses (mirrors M-006 `TranslatableEntityContractTest` + M-007 `ListingResolverContract`). Integration tests in `tests/Integration/PhaseN/` (verify next phase number at task-outline). End-to-end fixture: Minoo `teaching` two-axis. In-memory storage via `InMemoryEntityStorage` + `DBALDatabase::createSqlite()` for backend conformance. |
| **Target Platform** | PHP CLI + PHP-FPM under Caddy in production; PHP built-in server in dev. Linux x86_64 / WSL2. |
| **Project Type** | Monorepo PHP framework (62 packages, 7 layers). Touched layers: **L1** (`entity-storage` schema + driver + save/load surface; `entity` exception factory addition), **L6** (`cli` migration generator extension). No new packages. No upward edges. |
| **Performance Goals** | NFR-A non-translatable read fallback ≤ 1 extra row lookup vs single-axis read. NFR-B multi-language atomic save scales linearly with translation count (no quadratic joins). NFR-C revision-list query (`listRevisions(?$langcode)`) bounded by `OFFSET + LIMIT` (no full scan). NFR-D zero new PHPStan / PHPUnit warnings (level 5). |
| **Constraints** | C-001 layer graph unchanged: `entity-storage` (L1) → `entity` (L1) → `foundation` (L0). `cli` (L6) → `entity-storage` (L1). No upward edges. C-002 backward compatibility: single-axis revisionable-only and single-axis translatable-only types behave identically; only types declaring **both** revisionable + translatable get the composite-PK shape. C-003 `RevisionTableBuilder` extended (not forked) — single-axis surrogate-PK path retained. C-004 `EntityTranslationException` is the canonical translation-domain exception (no new exception class per FR-040). C-005 listing pipeline (M-007) is consumed unchanged — no new `ListingDefinition::langcode` value-object field (FR-030). C-006 vector / remote backends with translatable fields are forbidden — boot-time guard (FR-006). |
| **Scale / Scope** | 49 FRs, 4 NFRs, 6 Cs, 8 WPs. Touches `packages/entity-storage/src/Schema/RevisionTableBuilder.php` (extend), `packages/entity-storage/src/Schema/TranslationSchemaHandler.php` (extend for per-revision blob rows), `packages/entity-storage/src/SaveContext.php` (`withTranslations(array)`), `packages/entity-storage/src/Driver/RevisionableStorageDriver.php` (langcode-aware read/write), `packages/entity-storage/src/Exception/StorageMigrationException.php` (NEW), `packages/entity/src/Exception/EntityTranslationException.php` (new factory `historicalRevisionWrite`), `packages/cli/src/Handler/AddTranslationsMigrationGenerator.php` (extend for two-axis), `packages/cli/src/Handler/AddRevisionsMigrationGenerator.php` (NEW), `docs/specs/entity-storage-two-axis.md` (NEW canonical), `docs/cookbook/translatable-revisionable-entities.md` (NEW), charter §5.3 amendment, surface-map sync. |

## Charter Check

| Charter section | Gate | Status | Notes |
|---|---|---|---|
| **Testing Standards** | Contract + integration tests for new public surface. | PASS | Spec §3.10 mandates Minoo `teaching` round-trip (FR-043) + per-language access fixture (FR-044). WP01/WP02 ship sql-column + sql-blob backend conformance contract tests. |
| **Quality Gates** | `composer phpstan` level 5, `composer cs-check`, `bin/check-package-layers`, `bin/check-composer-policy` green. | PASS | All additive surface in L1 + L6; no upward edges; layer check clean. M-006 unified-exception pattern preserved. |
| **Performance Benchmarks** | NFR thresholds quantified. | PASS | NFR-A..D defined in Technical Context. NFR-A backed by one-row fallback rule (FR-005). |
| **Branch Strategy** | Plan/base/merge explicit and matched. | PASS | main → main → main. `branch_matches_target = true`. |
| **DIR-001 / DIR-002 / DIR-003** | Project directives. | PASS | No mission-specific override needed. |
| **Paradigm: domain-driven-design** | Entity / value-object / repository discipline. | PASS | `SaveContext` (value object — extended), composite-PK revision table is a storage schema decision (repository internal), `EntityTranslationException` / `StorageMigrationException` are domain exceptions with factory methods. |
| **Charter §5.3 (stable surface)** | Two-axis schema shape, `SaveContext` extensions, `listRevisions($langcode)` signature, new factory + exception class land on stable-surface map at mission close. | DEFERRED | Amendment lives in WP08. Spec §4 enumerates the surface. |
| **ADR 016 / ADR 017 alignment** | Governing ADRs. | PASS | ADR 017 §"Revision × translation interaction" mandates per-(entity, langcode) revisions; this plan ships it. ADR 016 deferred two-axis to v1.x; ADR 017 reversed the deferral. |

**Re-evaluation post-Phase-1**: All gates re-checked after `data-model.md` and `contracts/` generation. PASS unchanged.

## Project Structure

### Mission documentation

```
kitty-specs/entity-storage-translatable-revisions-01KRCDEE/
├── spec.md                          # 557 lines, revalidated 2026-05-17 (commit de5c6eba6)
├── plan.md                          # this file
├── research.md                      # Phase 0 — R-01..R-09 decisions
├── data-model.md                    # Phase 1 — composite PK + per-revision row shapes
├── quickstart.md                    # Phase 1 — Minoo teaching two-axis walkthrough
├── contracts/                       # Phase 1 — stable-surface contracts
│   ├── composite-pk.md              # (entity_id, langcode, vid) semantics + default-langcode anchor
│   ├── save-context-translations.md # SaveContext::withTranslations(array) builder
│   ├── exception-surface.md         # EntityTranslationException factories + StorageMigrationException
│   ├── access-policy-revision.md    # view_revision signature (resolves §9 Q7)
│   └── two-axis-migration.md        # Migration generator behaviour + --add-revisions flag
├── meta.json
└── status.events.jsonl
```

### Source paths touched

```
packages/entity/                                                              # L1 — exception factory addition
└── src/Exception/EntityTranslationException.php                              # WP04 — +historicalRevisionWrite($vid, $langcode)

packages/entity-storage/                                                       # L1 — primary surface
├── src/
│   ├── SaveContext.php                                                       # WP03 — +withTranslations(array) builder
│   ├── Schema/
│   │   ├── RevisionTableBuilder.php                                          # WP01 — extend for composite (tid, langcode, vid) PK on two-axis types
│   │   └── TranslationSchemaHandler.php                                      # WP02 — extend for per-revision blob rows (sql-blob)
│   ├── Driver/
│   │   └── RevisionableStorageDriver.php                                     # WP03/WP04 — langcode-aware writeRevision / readRevision signatures
│   ├── RevisionableSqlBlobStorage.php                                        # WP02 — per-revision blob row writes
│   ├── Listing/
│   │   └── TwoAxisFilterResolver.php                                         # WP07 — route Filter::langcode() through per-(entity, langcode) current-revision pointer
│   └── Exception/
│       └── StorageMigrationException.php                                     # WP04 — NEW (factories: noOpPromotion, unsupportedTwoAxisField)
└── tests/
    ├── Contract/
    │   ├── TwoAxisStorageContract.php                                        # WP01-WP04 — abstract (CoversNothing)
    │   ├── SqlColumnTwoAxisStorageTest.php                                   # WP01 — sql-column backend conformance
    │   └── SqlBlobTwoAxisStorageTest.php                                     # WP02 — sql-blob backend conformance
    └── Unit/
        ├── SaveContextTranslationsTest.php                                   # WP03 — withTranslations builder
        ├── HistoricalRevisionWriteTest.php                                   # WP04 — exception factory
        └── StorageMigrationExceptionTest.php                                 # WP04/WP06 — factories

packages/cli/                                                                  # L6 — generator extension
├── src/Handler/
│   ├── AddTranslationsMigrationGenerator.php                                 # WP06 — extend: revisionable-only → two-axis path
│   └── AddRevisionsMigrationGenerator.php                                    # WP06 — NEW (translatable-only → two-axis path; flag --add-revisions)
└── tests/
    └── Handler/
        ├── AddTranslationsMigrationGeneratorTwoAxisTest.php                  # WP06
        └── AddRevisionsMigrationGeneratorTest.php                            # WP06

packages/access/                                                               # L1 — no source change; access composition is documentation-only
└── (no source touched — view_revision + translate compose on translation instance; documented in contracts/access-policy-revision.md)

tests/Integration/PhaseN/                                                      # NEW (verify next-phase number at task-outline)
├── TwoAxisSchemaIntegrationTest.php                                          # WP01+WP02
├── TwoAxisSaveLoadIntegrationTest.php                                        # WP03+WP04
├── TwoAxisAccessPolicyIntegrationTest.php                                    # WP05 — Coordinator vs Knowledge-Keeper fixture
├── TwoAxisMigrationGeneratorIntegrationTest.php                              # WP06
├── TwoAxisListingInvalidationIntegrationTest.php                             # WP07
└── MinooTeachingTwoAxisE2ETest.php                                           # WP08 — FR-043 + FR-044 (the validation gate)

docs/
├── specs/
│   ├── entity-storage-translatable-revisions.md                              # WP08 — post-mortem stamp; canonical doctrine
│   ├── entity-storage-two-axis.md                                            # WP08 — NEW canonical spec (FR-045)
│   ├── public-surface-map.md                                                 # WP08 — register two-axis schema, SaveContext::withTranslations, StorageMigrationException
│   ├── public-surface-map.php                                                # WP08 — mirror
│   └── stability-charter.md                                                  # WP08 — §5.3 amendment
├── cookbook/
│   └── translatable-revisionable-entities.md                                 # WP08 — NEW (FR-046)
└── upgrade-guides/
    └── <alpha-N>-two-axis.md                                                 # WP08 — NEW (FR-047)

CLAUDE.md                                                                      # WP08 — orchestration row update for two-axis paths
CHANGELOG.md                                                                   # WP08 — [Unreleased] Added bullet
```

## Phase 0: Research

See [`research.md`](research.md). Decisions captured:

1. **R-01 Composite PK strategy.** Use composite `(tid, langcode, vid)` in the translation-revision table; surrogate `vid INTEGER PRIMARY KEY` retained on the entity-revision table (default-langcode revision). Rationale: surrogate PK on revision-id-only table gives a stable monotonic id usable in cross-langcode interleaved `listRevisions()` ordering; composite PK on translation-revision table ensures per-langcode revision lineage. Resolves §9 Q4 (default-langcode pointer redundancy is intentional: fast single-query primary load).
2. **R-02 RevisionTableBuilder extension vs fork.** EXTEND — add a constructor flag (or per-call parameter) to switch to composite-PK output when the entity type is both translatable + revisionable. Single-axis types retain surrogate PK output unchanged. Rationale: forking creates a maintenance fork; the divergence at the SQL emission site is small (composite UNIQUE + langcode column).
3. **R-03 Non-translatable field storage strategy.** **Stored once on the default-langcode entity-revision row** (per §9 Q1 recommendation, now committed). Other-langcode revisions read non-translatable fields via single-step fallback to the default-langcode current-revision row. FR-004 + FR-005 normative. Rationale: storage savings (5 languages × 50 revisions = 250 translation-revision rows; non-translatable fields stored once across 50 entity-revision rows, not 250); editorial integrity (changing a non-translatable field doesn't fragment translation revision history).
4. **R-04 SaveContext::withTranslations(array) shape.** Returns new immutable `SaveContext` carrying `?array $translations = null` (list of langcodes); coordinator iterates langcodes inside a single transaction. Mutually exclusive with `withLangcode()` at the call site (if both set, `withTranslations` takes precedence and `langcode` is ignored). Validator rejects empty arrays.
5. **R-05 Load semantics — revision × langcode lookup.** `$storage->load($type, $id)` loads the entity at the default-langcode current revision. `$entity->getTranslation('fr')` switches active langcode + reads the fr current-revision pointer + assembles the translation instance. `$translation->loadRevision(7)` produces a historical instance whose `isCurrentRevision()` is false and whose `save()` raises `EntityTranslationException::historicalRevisionWrite`. Composition is interface-only — no new top-level interface.
6. **R-06 Exception consolidation.** Follow M-006's pattern: single `EntityTranslationException` class with static factory methods. Add new factory `historicalRevisionWrite($vid, $langcode)` (FR-017 / FR-040). Reuse existing `cannotRemoveDefault()`, `translationNotFound()`. Ship one new exception class `StorageMigrationException` (factories: `noOpPromotion($entityType)` for FR-029; `unsupportedTwoAxisField($fieldName, $backend)` for FR-006). No `HistoricalRevisionWriteException`, no `UnsupportedTwoAxisFieldException`, no `NoOpMigrationException` separate classes — the original spec's "five exception classes" plan dropped per M-006 reconciliation.
7. **R-07 Access policy composition (`view_revision` signature) — §9 Q7 resolved.** Policy methods receive the translation instance as the entity (existing convention) PLUS an optional `?RevisionableEntityInterface $revision = null` parameter so policies can introspect `$revision->revisionAuthor()` / `revisionCreatedAt()` without a second lookup. When called for a non-revision operation (`view`, `edit`), `$revision` is null. Documented in `contracts/access-policy-revision.md`. `translate` operation on a two-axis instance falls back to `edit` per ADR 017 + FR-022.
8. **R-08 Listing pipeline integration — M-007 substrate sufficient.** Confirmed: `Filter::langcode()`, `ListingCacheInvalidator`, `language.content` cache context, `ListingDefinition::isTranslatable()` detection all shipped. M-004 WP07 adds **one** new component: `TwoAxisFilterResolver` (or equivalent hook in the existing resolver) that routes a `Filter::langcode('oj')` query against a two-axis entity type to the per-`(entity, langcode)` current-revision pointer instead of the entity-level primary current-revision pointer. FR-033a captures this normatively. No changes to `ListingDefinition` shape; no new value-object field.
9. **R-09 Migration generator — extend vs fork.** EXTEND `AddTranslationsMigrationGenerator` to detect when the target entity type is revisionable + asked to add translations → emit two-axis migration template (composite-PK translation-revision table + per-langcode current-revision pointer column on translation table + backfill of existing default-langcode revisions). Ship a sibling `AddRevisionsMigrationGenerator` (new class) for the `--add-revisions` flag because the input shape (translatable-only) is different enough to justify a sibling rather than a third branch in the existing generator. Reverse migration documented per FR-028. `NoOpMigrationException` consolidated into `StorageMigrationException::noOpPromotion`.

## Phase 1: Design

See:
- [`data-model.md`](data-model.md) — composite PK shapes + per-revision row layouts + `SaveContext` extension + exception factory signatures
- [`contracts/`](contracts/) — stable-surface contracts (5 files)
- [`quickstart.md`](quickstart.md) — Minoo `teaching` two-axis walkthrough (FR-043 demo + access fixture preview)

Re-evaluating Charter Check after Phase 1 design: PASS unchanged.

## Risks & Open Questions

### Top risks

| # | Risk | Mitigation |
|---|---|---|
| R-A | **`RevisionTableBuilder` extension breaks single-axis callers.** Single-axis revisionable-only types ship the surrogate-PK shape today (M-006); a careless rewrite risks subtle PK regressions in M-006 consumers. | Extend via constructor / per-call flag; default path emits the existing single-axis SQL byte-for-byte. Contract test: `SingleAxisRevisionTableBuilderTest` (existing) MUST pass unchanged. WP01 acceptance includes "M-006 single-axis revision tests green." |
| R-B | **Non-translatable field fallback reads add a join on every read of a non-default-langcode translation.** FR-005's "single-step fallback" doubles query count for non-default-langcode loads. NFR-A bounds the overhead at one extra lookup. | Acceptable for v0.x — Minoo's editorial fan-out is small. Cookbook (FR-046) documents the cost and explains pruning as a near-mandatory operational practice for high-edit entities (FR-039 default-off). Future perf mission may add per-load join. |
| R-C | **Multi-language atomic saves widen transaction lock footprint.** §9 Q3 flagged. A 10-language save holds locks across 11 row writes (1 entity-revision + 10 translation-revisions) plus index updates. | Stay atomic in v0.x (per spec recommendation). FR-013 mandates `PartialSaveException` on failure. Cookbook documents the cost. Revisit if a real consumer hits the wide-save case. |

### §9 open questions resolved in this plan

| § | Question | Resolution |
|---|---|---|
| §9 Q1 | Non-translatable field storage strategy. | **Stored once on default-langcode entity-revision row.** FR-004 + FR-005 normative. Other-langcode revisions read via single-step fallback. Captured in R-03. |
| §9 Q3 | Multi-language save atomicity. | Stay atomic in v0.x; revisit if a real consumer hits 10+ language saves. Captured in R-A / risk register. |
| §9 Q4 | Default-langcode pointer redundancy. | Keep both `<table>.vid` (entity-level) and `<table>__translation.vid` for `(tid, default_langcode)`. Enforce sync in `RevisionableStorageDriver`. Captured in R-01. |
| §9 Q6 | Listing pipeline cache invalidation granularity. | Emit BOTH langcode-less and langcode-scoped tags (FR-032). Already M-007's behavior. |
| §9 Q7 | `view_revision` policy method signature. | Pass optional `?RevisionableEntityInterface $revision` parameter so policies can introspect revision metadata without a second lookup. Documented in `contracts/access-policy-revision.md`. Captured in R-07. |

### Remaining open questions (informational, not blocking the plan)

- §9 Q2 (pruning policy default-langcode interactions) — recommendation in spec is normative for WP08 cookbook guidance; no FR change needed.
- §9 Q5 (translation soft-delete) — hard delete in v0.x per spec; soft-delete is app concern.
- §9 Q8 (performance — revision count growth) — cookbook discusses pruning as near-mandatory operational practice for high-edit entities.

## Phases / Milestones

| Phase | Status | Date |
|---|---|---|
| Specify (revalidated post-M-006 + M-007) | ✅ DONE | 2026-05-17 (commit `de5c6eba6`) |
| Plan (this file) | 🔄 IN PROGRESS | 2026-05-16 |
| Tasks outline | ⏳ pending | — |
| Tasks packages | ⏳ pending | — |
| Tasks finalize | ⏳ pending | — |
| Implement-review loop | ⏳ pending | — |
| Merge | ⏳ pending | — |

### Recommended dispatch order (from §12.6 of spec)

1. **WP01 + WP02** (parallel) — schema substrate (sql-column + sql-blob).
2. **WP03 + WP04 + WP06** (parallel after WP01+WP02) — save semantics + load semantics + migration generator.
3. **WP05** — access policy composition (depends on WP04 load semantics).
4. **WP07** — listing integration (depends on WP03 save events + WP04 read-at-langcode-revision).
5. **WP08** — close-out validation + documentation.

## Dependencies

Both hard prerequisites have shipped (revalidated 2026-05-17):

- **M-006 `entity-storage-translations-v1`** — shipped 2026-05-13/14 (PR #1485 / `a7840a36a`). Provides `TranslatableInterface`, `TranslatableEntityTrait`, `ContentEntityBase implements TranslatableInterface`, `FieldDefinition::translatable()`, `SaveContext::withLangcode()`, `AfterSaveEvent::affectedLangcodes()`, `EntityTranslationException` (5 existing factories), `TranslationSchemaHandler`, `AddTranslationsMigrationGenerator`.
- **M-007 `listing-pipeline-v1`** — shipped 2026-05-16. Provides `Filter::langcode()`, `ListingCacheInvalidator` (emits `entity:<type>:<id>:<langcode>` + langcode-less tags), `language.content` cache-context auto-injection, `ListingDefinition` langcode-aware.
- **M-006 single-axis revisions substrate** (shipped concurrently with translations): `RevisionableEntityInterface`, `RevisionableEntityTrait`, `RevisionableStorageDriver`, `RevisionTableBuilder`, `RevisionPruningPolicy`, `RevisionMetadata`, `RevisionableSqlBlobStorage`.

No external (non-Waaseyaa) dependency changes required.

## Complexity Tracking

| Item | Why it could be complex | Mitigation |
|---|---|---|
| Composite-PK migration of `RevisionTableBuilder` | Single-axis revision tables already exist in the wild (M-006 shipped); changing the table builder risks regressing M-006 tests if the wrong code path is taken. | R-02: extend not fork; default path byte-for-byte unchanged. Contract test gates the M-006 single-axis behavior. |
| Non-translatable field fallback adds a join | Every read of a non-default-langcode translation joins to the entity-revision table to fetch non-translatable values. | One extra lookup per load is acceptable (NFR-A). Cookbook documents. Future perf mission may eliminate the join. |
| Multi-language atomic save transaction scope | 10-language save holds locks across 11 row writes + index updates. | Atomic in v0.x; `PartialSaveException` on partial failure (FR-013). Cookbook flags as operational consideration. |
| Listing pipeline routing for two-axis types | `Filter::langcode('oj')` against a two-axis type must read each result entity at the langcode's current revision, not the entity-level primary current revision (FR-033a). | One new component (`TwoAxisFilterResolver` or equivalent hook). No changes to `ListingDefinition` shape; no parallel API. M-007 substrate fully consumed unchanged. |
| Migration generator branching | `AddTranslationsMigrationGenerator` now handles three input shapes (non-translatable-non-revisionable → translatable; revisionable-only → two-axis; non-translatable → translatable). | Extend existing generator for revisionable-only → two-axis branch; ship new sibling `AddRevisionsMigrationGenerator` for the inverse (translatable-only → two-axis) to keep generator branches simple. |
| `view_revision` policy signature change | Adding `?RevisionableEntityInterface $revision = null` to policy method signatures could surprise existing single-axis revisionable consumers. | Parameter is optional and defaults to null; existing single-axis policies that don't introspect revision metadata continue to work. Documented in `contracts/access-policy-revision.md`. |

## References

- [ADR 017](../../docs/adr/017-per-field-translation.md) — governing decision (Accepted 2026-05-11), §"Revision × translation interaction" specifically.
- [ADR 016](../../docs/adr/016-revisions-first-class.md) — single-axis revisions; this mission composes them.
- [ADR 010](../../docs/adr/010-multi-backend-field-storage.md) — backend restriction (vector / remote forbidden for translatable).
- [ADR 011](../../docs/adr/011-entity-lifecycle-events.md) — lifecycle events per translation.
- [ADR 015](../../docs/adr/015-listing-pipeline-views-equivalent.md) — listing pipeline; M-007 consumed substrate.
- M-006 mission: `kitty-specs/entity-storage-translations-v1-01KRF0FQ/`
- M-007 mission: `kitty-specs/listing-pipeline-v1-01KRMN0B/`
- Charter: [`stability-charter.md`](../../docs/specs/stability-charter.md) §5.3
- Minoo milestone #21 — Anishinaabemowin Localization — canonical consumer use case.
- 2026-05-11 framework/app audit (`waaseyaa/minoo/docs/audits/2026-05-11-framework-app-audit.md`).

## ⛔ Mandatory Stop

This command (`/spec-kitty.plan`) is COMPLETE after generating the planning artifacts above. The next commands are `/spec-kitty.tasks-outline` → `/spec-kitty.tasks-packages` → `/spec-kitty.tasks-finalize` → implement-review loop dispatch.
