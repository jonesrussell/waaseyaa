<!-- Spec reviewed 2026-05-17 - post-mission stamp: M-004 shipped; canonical doctrine moved to entity-storage-two-axis.md -->
# Entity Storage — Translatable + Revisionable Two-Axis Interaction

> **✅ SHIPPED — M-004 closed 2026-05-17 (canonical spec moved).**
>
> This mission spec captured the **plannable** state of two-axis storage between
> ADR 017 (per-field translation, Accepted 2026-05-11) and the M-004 implement-review
> loop. M-004 (`entity-storage-translatable-revisions-01KRCDEE`) shipped all eight
> work packages: WP01 (sql-column two-axis schema), WP02 (sql-blob two-axis schema),
> WP03 (`SaveContext::withTranslations()` + atomic multi-language save),
> WP04 (two-axis load + deletion + `StorageMigrationException` + `EntityTranslationException::historicalRevisionWrite()`),
> WP05 (two-axis access policy composition via `RevisionPolicyComposition`),
> WP06 (`make:storage-migration --add-revisions` flag), WP07 (`TwoAxisFilterResolver`
> listing-pipeline integration), WP08 (this docs closure).
>
> **For canonical doctrine** — schema shapes, save/load algorithms, exception
> surface, listing integration, performance notes, operator cookbook — see
> [`entity-storage-two-axis.md`](entity-storage-two-axis.md). The cookbook lives at
> [`../cookbook/translatable-revisionable-entities.md`](../cookbook/translatable-revisionable-entities.md);
> the upgrade guide for the introducing alpha train is at
> [`../upgrade-notes/two-axis-storage.md`](../upgrade-notes/two-axis-storage.md).
>
> The rest of this document is preserved as the **planning artefact** —
> spec, plan input, and audit trail. Day-to-day operator and integrator questions
> should be answered against the canonical spec, not this mission spec.

> **✅ FULLY UNBLOCKED — PLANNABLE (revalidated 2026-05-17 against shipped substrates)**
>
> Both hard prerequisites have shipped:
>
> 1. ✅ **Single-axis translation substrate** — M-006 (`entity-storage-translations-v1-01KRF0FQ`, squash `0f7e1809a` on 2026-05-13, mission closed in PR #1485 / `a7840a36a` on 2026-05-14). Concrete surface: `TranslatableInterface`, `TranslatableEntityTrait`, `ContentEntityBase implements TranslatableInterface` composition, `FieldDefinition::translatable()` + `isTranslatable()`, `TranslationSchemaHandler` (sql-blob + sql-column), `SaveContext::langcode` + `withLangcode()`, `AfterSaveEvent::affectedLangcodes()`, unified `EntityTranslationException` (factory methods: `translationNotFound`, `cannotRemoveDefault`, `notTranslatable`, `translationAlreadyExists`, `langcodeRequired`), and the `make:migration --add-translations` flag (`AddTranslationsMigrationGenerator`). See [`entity-storage-translations-v1.md`](entity-storage-translations-v1.md).
> 2. ✅ **Listing pipeline (ADR 015)** — M-007 (`listing-pipeline-v1-01KRMN0B`, shipped 2026-05-16). Concrete surface: `Filter::langcode()` canonical factory, `ListingCacheInvalidator` emitting `entity:<type>:<id>:<langcode>` tags from `AfterSaveEvent::affectedLangcodes()`, `language.content` cache context auto-injected when the entity type is translatable, full listing pipeline available. See [`listing-pipeline-v1.md`](listing-pipeline-v1.md).
>
> All eight WPs are now plannable. WP07's listing-pipeline integration becomes "extend M-007's canonical surface" rather than "design from scratch" — see §"Revalidation 2026-05-17" (§12) for substrate audit findings and FR/WP deltas.
>
> See `kitty-specs/entity-storage-translatable-revisions-01KRCDEE/spec.md` for the same banner and the §12 audit.

**Status:** Draft mission spec (2026-05-11), **FULLY UNBLOCKED 2026-05-17** (prereq 1 M-006 shipped 2026-05-13/14; prereq 2 M-007 `listing-pipeline-v1` shipped 2026-05-16; revalidated against both substrates 2026-05-17 — see §12)
**Audience:** framework maintainers; input for Spec Kitty `specify` → `plan` → `tasks` flow
**Mission ID:** TBD (to be assigned by `@jonesrussell` on mission creation)
**Origin:** [ADR 017](../adr/017-per-field-translation.md) §"Revision × translation interaction" (Accepted 2026-05-11).

**Governing ADRs:** [ADR 016](../adr/016-revisions-first-class.md) + [ADR 017](../adr/017-per-field-translation.md).

**Charter linkage:**
- [`stability-charter.md`](stability-charter.md) §5.3 governs the existing entity surface; this mission ships the substrate that composes revisions × translations.
- Not a beta-gate. Beta entry §3.2.8 requires single-axis revisions; two-axis is a v1.x quality-of-life mission for editorial-language-heavy consumers (Minoo and sibling Indigenous-language platforms).

**Sibling missions:**
- [`entity-storage-v2.md`](entity-storage-v2.md) — **hard prerequisite.** Single-axis revisions (revisionable-only entity types) and single-axis translations (translatable-only entity types) MUST ship first. This mission composes them.
- [`migration-platform-v1.md`](migration-platform-v1.md) — independent. When a migration writes to a two-axis entity type, `EntityDestination` writes per-language; the migration platform doesn't need to know.
- [`config-management-v1.md`](config-management-v1.md) — independent. Config entities are not in scope; CMI is config-only.

---

## 0. Origin

ADR 017 made the hardest single decision in the framework: revisionable+translatable entity types use **per-(entity, langcode) revisions**, not single-revision-spans-all-languages. The reasoning was Minoo's Knowledge Keeper editorial flow — Elder edits to the Anishinaabemowin translation must not invalidate English revision history.

ADR 016 explicitly deferred revisionable+translatable to v1.x. ADR 017 reversed that deferral on the grounds that Minoo's `teaching` is the canonical use case for both, and forcing a pick-one would either lose editorial integrity (no revisions on translated content) or lose Anishinaabemowin localization (no translation on revisioned content). Neither is acceptable.

The reversal commits the framework to ship the two-axis substrate. This mission ships it. It is the smallest of the four post-charter framework missions because it operates on a foundation that entity-storage-v2 has already laid — revisions on non-translatable types, translations on non-revisionable types. The two-axis composition is the missing piece.

---

## 1. Goals / non-goals

### 1.1 Goals

1. Define **schema shape** for revisionable+translatable entity types: revision tables keyed on `(entity_id, langcode, vid)`, with non-translatable fields stored once (on the default-langcode revision) and referenced by other-langcode revisions.
2. Specify **save semantics**: saving the French translation creates a new revision of the French translation only; other-language revision counts unchanged.
3. Specify **load semantics**: `$entity->getTranslation('fr')->loadRevision(3)` returns revision 3 of the French translation.
4. Compose **access policies**: `view_revision` and `translate` operations apply normally to the translation instance; policies can introspect `langcode` for per-language access decisions.
5. Extend the **storage migration generator** to promote single-axis types to two-axis (add translation to revisionable; add revisions to translatable).
6. Extend the **listing pipeline** (ADR 015) with per-langcode scoping and cache-tag inclusion of langcode.
7. Specify **lifecycle event semantics** for per-translation saves.
8. Validate the mission with **Minoo `teaching` end-to-end**: English and Anishinaabemowin revisions independent.

### 1.2 Non-goals

- **New entity types.** This mission ships substrate; new types ship as consumer-app work.
- **Admin UI for managing translation × revision history.** Future ADR if demand emerges.
- **Workflow / moderation across languages.** Separate future ADR. Revisions are the substrate; workflows are layered on top.
- **Vector-backed fields on two-axis types.** Forbidden in v0.x. The vector backend's per-langcode semantics are unclear and not in scope.
- **Cross-translation diff** ("what changed in the English revision since the French was last translated"). A useful editorial feature; deferred.
- **Auto-translation / machine translation integration.** Out of scope; ADR 017 explicitly defers translation providers.

---

## 2. Scope summary

### 2.1 In scope

- Revision-table schema for two-axis types (sql-blob + sql-column).
- Non-translatable field storage rule: stored once on default-langcode revision; other-langcode revisions reference.
- Save semantics: per-(entity, langcode) revision creation; SaveContext extension with langcode.
- Load semantics: `getTranslation(langcode)->loadRevision(vid)` composition.
- Multi-language save in one transaction via `SaveContext::withTranslations()`.
- Access policy composition: `view_revision` + `translate` ops on the translation instance.
- Storage migration generator extensions: `--add-translations` and `--add-revisions` flags.
- Reverse migration support (with data-loss documentation).
- Listing pipeline extension: per-langcode filter, langcode in cache tags.
- Lifecycle events: per-translation save events with langcode in payload.
- Translation deletion semantics: deleting a non-default translation deletes its revisions; deleting default-langcode requires deleting the entity.
- Revision pruning extension: per-language pruning policies.
- Documentation: spec, cookbook, upgrade guide, charter cross-reference.

### 2.2 Out of scope

(See §1.2 non-goals.)

---

## 3. Functional requirements

Normative requirements use **MUST / SHOULD / MAY** per RFC 2119. Numbered for Spec Kitty tokenization.

### 3.1 Schema shape

- **FR-001** Revision tables for two-axis entity types MUST key on `(entity_id, langcode, vid)`. A composite primary key, not a surrogate.
- **FR-002** For `sql-column` backend, two-axis types use a primary table + a translation table + a per-translation revision table. Schema (illustrative, for `teaching`):
  - `teaching` — one row per entity; tracks default-langcode current-revision-vid + current-default-langcode.
  - `teaching__translation` — one row per (entity, langcode); tracks current-revision-vid per language.
  - `teaching__translation__revision` — one row per (entity, langcode, vid); stores translatable field values.
  - `teaching__revision` — one row per default-langcode revision; stores non-translatable field values.
- **FR-003** For `sql-blob` backend, two-axis types use a primary table + a translation-revision table keyed on `(entity_id, langcode, vid)`. Field values live in `_data` blob per row.
- **FR-004** Non-translatable field values MUST be stored once, on the default-langcode revision. Other-langcode revisions MUST NOT duplicate non-translatable field values.
- **FR-005** Non-translatable field reads from a non-default-langcode revision MUST follow a single-step fallback to the corresponding default-langcode revision's value.
- **FR-006** Field-level backend selection (per ADR 010 `FieldDefinition::storedIn()`) MUST be honored. Translatable fields on a non-`sql-blob`/`sql-column` backend (e.g. `vector`) raise `UnsupportedTwoAxisFieldException` at boot.
- **FR-007** Each `(entity, langcode)` MUST track its current-revision-vid independently. Saving the French translation updates only the French current-revision pointer.
- **FR-008** The entity-level "primary current revision" MUST be the default-langcode current revision. Reads without a `getTranslation()` call return this.

### 3.2 Save semantics

- **FR-009** A save of `$entity->getTranslation('fr')` MUST create a new revision of the French translation only. The English (default-langcode) revision-vid does not change.
- **FR-010** Other-language current-revision pointers MUST NOT change as a side effect of saving one translation.
- **FR-011** A save that mutates a non-translatable field MUST create a new default-langcode revision (storing the new non-translatable value). Other-language current revisions continue to reference the latest default-langcode revision for non-translatable values; they do not need new revisions of their own.
- **FR-012** `SaveContext` MUST gain a `langcode` field. When unset, save targets the entity's current `activeLangcode()` (which defaults to the entity's `defaultLangcode()`).
- **FR-013** Multi-language saves MUST be possible via `SaveContext::withTranslations(array $langcodes)`. All saves in the set run in one transaction; partial failure rolls back the whole set with `PartialSaveException` (per ADR 010 §6.5).
- **FR-014** Lifecycle events (`BeforeSaveEvent` / `AfterSaveEvent`, per ADR 011) MUST fire per saved translation. A multi-language save firing four translations fires four pairs of events. Each event carries the saved langcode in `SaveContext`, and the existing `AfterSaveEvent::affectedLangcodes()` (shipped by M-006) MUST list every langcode written in the save — for multi-language atomic saves this is the full list. Listing-cache invalidation (M-007 `ListingCacheInvalidator`) consumes this field unchanged.

### 3.3 Load semantics

- **FR-015** `$storage->load($entityType, $id)` MUST return the entity with its default-langcode current revision active.
- **FR-016** `$entity->getTranslation($langcode)` MUST return the entity with that langcode's current revision active. If no translation exists, raise `EntityTranslationException::translationNotFound($langcode)` (M-006 established factory; reused unchanged).
- **FR-017** `$entity->getTranslation($langcode)->loadRevision($vid)` MUST return a specific revision of that translation. The returned entity is in a "historical" state; saves on it are forbidden (raise `EntityTranslationException::historicalRevisionWrite($vid, $langcode)` — new factory on M-006's exception class, see FR-040).
- **FR-018** `$entity->listRevisions()` MUST return revisions of ALL languages in interleaved descending-creation order. `$entity->listRevisions($langcode)` scopes to one language.
- **FR-019** `$entity->translations()` MUST return langcodes that have at least one revision. Languages with translations that have been fully purged via pruning MUST NOT appear.

### 3.4 Access policy composition

- **FR-020** `view_revision` and `translate` access operations apply to the translation instance, not to the language-agnostic entity. A policy's method receives the translation instance and may introspect `$entity->activeLangcode()` for per-language access decisions.
- **FR-021** Policies that do NOT declare `view_revision` MUST fall back to `view` per ADR 016 FR-040. Same fallback applies for translations: `view_revision` on the French translation falls back to `view` on the French translation.
- **FR-022** Policies that do NOT declare `translate` MUST fall back to `edit` per ADR 017 §"Translation operation." Same fallback applies for revisions on translations.
- **FR-023** The framework MUST NOT add a new `view_translation_revision` operation. The composition of `view_revision` + langcode introspection is sufficient and clearer.
- **FR-024** Worked example (Minoo): a policy may grant `view_revision` on the English revision to Coordinators but require Knowledge-Keeper role for `view_revision` on the Anishinaabemowin revision. The policy method tests `$entity->activeLangcode()` and applies different role checks. No new operation needed.

### 3.5 Migration generator extensions

- **FR-025** `bin/waaseyaa make:storage-migration <entity_type>` MUST gain the missing flag and extend the existing one for two-axis promotion:
  - `--add-translations` — **ALREADY SHIPPED** by M-006 (`packages/cli/src/Handler/AddTranslationsMigrationGenerator.php`) for revisionable-only → translatable. This mission MUST extend the generator so the same flag, when applied to a revisionable type, also creates the per-`(entity_id, langcode, vid)` translation-revision table (FR-001..FR-003) and backfills existing revisions as default-langcode revisions.
  - `--add-revisions` — **NEW IN THIS MISSION.** Adds revision support to a translatable-only type. When the target is translatable, it creates the translation-revision table keyed `(entity_id, langcode, vid)` and backfills the current translation rows as initial revisions per langcode.
- **FR-026** When promoting **revisionable-only → two-axis**: the migration creates the translation tables, backfills existing revisions as default-langcode revisions, sets per-(entity, langcode) current-revision pointers for the existing default-langcode revision.
- **FR-027** When promoting **translatable-only → two-axis**: the migration adds `vid` to the existing translation tables, creates a parallel translation-revision table, backfills the current translation values as initial revisions.
- **FR-028** Both promotions MUST be reversible by default. Reverse migration loses revision history for non-current revisions (documented in the migration file's docblock).
- **FR-029** Promoting an entity type that is already two-axis MUST fail with `NoOpMigrationException`.

### 3.6 Listing pipeline integration (ADR 015 / M-007 substrate)

The M-007 substrate already ships the langcode-aware listing surface (`Filter::langcode()`, `entity:<type>:<id>:<langcode>` cache tags via `ListingCacheInvalidator`, `language.content` cache context auto-injected when the entity type is translatable). This mission MUST verify and extend that surface for the two-axis case (per-`(entity, langcode)` revision pointers).

- **FR-030** Two-axis listings MUST use the existing `Filter::langcode($code)` canonical factory shipped by M-007. When applied, the listing returns the current revision of that langcode for each result entity. The mission MUST NOT add a new `ListingDefinition::langcode` value-object field; the canonical filter is the user-facing API.
- **FR-031** A listing of two-axis entities filtered by `Filter::langcode('oj')` MUST return only entities whose `(entity_id, 'oj')` translation row exists (i.e., the translation has at least one revision and is not removed). Entities without that translation are excluded.
- **FR-032** Cache-tag emission for two-axis saves MUST flow through the existing `AfterSaveEvent::affectedLangcodes()` → `ListingCacheInvalidator` path (no new invalidator). Saving the French translation of entity 42 MUST emit `entity:<type>:42` AND `entity:<type>:42:fr`; saving the default-langcode revision (when a non-translatable field changes) emits both `entity:<type>:42` and `entity:<type>:42:<default-langcode>`. Multi-language atomic saves emit one langcode-scoped tag per affected langcode plus the langcode-less tag.
- **FR-033** Cache contexts for two-axis listings MUST inherit M-007's `language.content` auto-injection (which already triggers when the entity type is translatable). The mission MUST NOT introduce a parallel `language.requested` token; M-007's canonical naming wins.
- **FR-033a** The two-axis filter resolver MUST integrate with the per-`(entity, langcode)` current-revision pointer: a `Filter::langcode('oj')` listing reads each result entity at the langcode's current revision, not at the entity-level "primary current revision."

### 3.7 Translation deletion

- **FR-034** `$entity->removeTranslation($langcode)` MUST delete the (entity, langcode) row and all its revisions. The translation is unrecoverable.
- **FR-035** Attempting to remove the default-langcode translation MUST raise `EntityTranslationException::cannotRemoveDefault($langcode)` (M-006 established factory; reused unchanged). To remove the default-langcode "translation," operators delete the whole entity (`$storage->delete([$entity])`).
- **FR-036** Removing a non-default translation MUST NOT affect other-language revisions or the entity itself.

### 3.8 Revision pruning extension

- **FR-037** Revision pruning policies (from ADR 016 `RevisionPruner`) MUST be extensible per-language. A `PruningPolicy` on a two-axis type MAY apply different keep-counts to different languages.
- **FR-038** Pruning MUST NEVER delete the current revision of any language.
- **FR-039** Pruning MUST be a no-op by default. Operators opt in per entity type with explicit configuration.

### 3.9 Error model

- **FR-040** The mission MUST follow M-006's established exception pattern (a single domain exception class with static factory methods), NOT introduce five separate exception classes:
  - Extend the existing `Waaseyaa\Entity\Exception\EntityTranslationException` with new factories: `historicalRevisionWrite($vid, $langcode)` (replaces the planned `HistoricalRevisionWriteException`), and reuse existing `cannotRemoveDefault($langcode)` for default-langcode removal attempts, `translationNotFound($langcode)` for missing translations.
  - Add `Waaseyaa\EntityStorage\Exception\StorageMigrationException` (single class, factories: `noOpPromotion($entityType)`, `unsupportedTwoAxisField($fieldName, $backend)`) for the two new error modes specific to this mission's migration generator and field-backend guard.
- **FR-041** Each factory MUST set a stable string `code` field per charter §4.4 (e.g. `'historical_revision_write'`, `'no_op_promotion'`, `'unsupported_two_axis_field'`).
- **FR-042** Renames or removals of these factory methods follow the deprecation cycle (charter §4); the exception classes themselves are stable surface.

### 3.10 Validation (mission-internal)

- **FR-043** WP07 MUST demonstrate Minoo `teaching` end-to-end:
  1. Create a teaching in English (default langcode).
  2. Add an Anishinaabemowin translation.
  3. Edit the English text three times — three new English revisions; one Anishinaabemowin revision.
  4. Edit the Anishinaabemowin text twice — two new Anishinaabemowin revisions; English revision count unchanged.
  5. Verify revision-list output: 5 revisions total, independently sequenced per langcode.
  6. Verify non-translatable field changes propagate correctly (changing `community_id` creates a new default-langcode revision; Anishinaabemowin reads see the new value via fallback).
- **FR-044** WP07 MUST demonstrate per-language access policy: a fixture Coordinator role sees English revision history but cannot see Anishinaabemowin revision history; the Knowledge-Keeper role sees both.

### 3.11 Documentation

- **FR-045** `docs/specs/entity-storage-two-axis.md` MUST exist post-mission as the canonical spec for the two-axis surface.
- **FR-046** `docs/cookbook/translatable-revisionable-entities.md` MUST ship — operator guide covering when to opt in, how access policies compose, performance implications.
- **FR-047** An upgrade-guide entry MUST ship for the alpha train that introduces two-axis support (per charter §7).
- **FR-048** Cross-reference from [`entity-storage-v2.md`](entity-storage-v2.md) and from this spec to the new canonical spec.

---

## 4. Stable surface deliverables

Maps the mission's stable-surface output to charter §5.3.

| Symbol | Kind | Notes |
|---|---|---|
| Two-axis schema shape (sql-blob + sql-column) | Storage schema | Stable surface per charter §5.3 special-case (multi-axis migration governance) |
| `SaveContext::langcode` field + `withTranslations(array)` builder | Method extension | Extension of existing SaveContext from entity-storage-v2 |
| `RevisionableEntityInterface::listRevisions($langcode = null)` parameter | Signature extension | Backwards compatible; existing callers unchanged |
| `EntityTranslationException::historicalRevisionWrite()` factory | Method on existing M-006 exception class | Reuses M-006 unified-exception pattern; no new exception class |
| `StorageMigrationException` (new class, factories: `noOpPromotion()`, `unsupportedTwoAxisField()`) | Exception class | New on stable surface; single class with factories (M-006 pattern) |
| `bin/waaseyaa make:storage-migration --add-revisions` flag (new); `--add-translations` extended to two-axis | CLI flag | Extends M-006's existing `AddTranslationsMigrationGenerator` |
| `Filter::langcode($code)` — already shipped by M-007 | Existing canonical filter | No change; this mission consumes it |
| Cache-tag format `entity:<type>:<id>:<langcode>` — already shipped by M-007 | Tag string convention | Already stable; this mission verifies two-axis emission |

No new top-level interfaces required. Two-axis composition is achieved by composing existing `RevisionableEntityInterface` + `TranslatableEntityInterface`.

---

## 5. Schema spec (normative)

### 5.1 sql-column shape

For an entity type `teaching` that is both `revisionable: true` and `translatable: true`:

```
teaching(
  tid INTEGER PRIMARY KEY,
  uuid TEXT,
  default_langcode TEXT,
  vid INTEGER,                    -- pointer to current default-langcode revision
  -- non-translatable fields (community_id, starts_at, etc.)
)

teaching__translation(
  tid INTEGER,
  langcode TEXT,
  vid INTEGER,                    -- pointer to current revision of this translation
  PRIMARY KEY (tid, langcode)
)

teaching__revision(
  vid INTEGER PRIMARY KEY,
  tid INTEGER,                    -- FK to teaching.tid
  revision_created_at TEXT,
  revision_author INTEGER,
  revision_log TEXT,
  -- non-translatable fields (snapshot for this revision)
)

teaching__translation__revision(
  vid INTEGER PRIMARY KEY,
  tid INTEGER,
  langcode TEXT,
  revision_created_at TEXT,
  revision_author INTEGER,
  revision_log TEXT,
  -- translatable fields (snapshot for this langcode at this revision)
)
```

### 5.2 sql-blob shape

```
teaching(
  tid INTEGER PRIMARY KEY,
  uuid TEXT,
  default_langcode TEXT,
  vid INTEGER,
  _data TEXT  -- JSON blob of non-translatable fields for current default-langcode revision
)

teaching__translation__revision(
  vid INTEGER PRIMARY KEY,
  tid INTEGER,
  langcode TEXT,
  revision_created_at TEXT,
  revision_author INTEGER,
  revision_log TEXT,
  _data TEXT  -- JSON blob of translatable fields for this langcode at this revision
)
```

Simpler; `_data` carries the per-langcode payload.

### 5.3 Field-level allocation rule

- A `FieldDefinition::translatable()` field's values live in the translation-revision table (column-backed) or translation `_data` blob (blob-backed).
- A non-translatable field's values live in the entity-revision table (column-backed) or primary table's `_data` blob (blob-backed).
- Reading a non-translatable field from a non-default-langcode context reads through the entity-revision row referenced by the default-langcode current-revision pointer.

### 5.4 Forbidden combinations

- A translatable field on the `vector` backend: forbidden. `UnsupportedTwoAxisFieldException` at boot.
- A translatable field on the `remote` backend: forbidden. Same.
- A non-translatable field on any backend: allowed (translation only affects translatable fields; non-translatable fields ride normal backend rules).

---

## 6. Save and load algorithms

### 6.1 Save single translation

```
SaveContext: { langcode: 'oj' }
Entity: teaching tid=42

1. Coordinator dispatches BeforeSaveEvent (langcode='oj').
2. Load the current default-langcode revision (for non-translatable fields).
3. If non-translatable fields changed: create new entity-revision row; update teaching.vid.
4. Create new translation-revision row keyed (vid, tid, langcode='oj').
5. Update teaching__translation row for (tid, langcode='oj') with new vid.
6. Coordinator dispatches AfterSaveEvent (langcode='oj').
```

### 6.2 Save multi-language atomically

```
SaveContext: withTranslations(['en', 'oj', 'fr'])
Entity: teaching tid=42

1. Open transaction.
2. For each langcode in ['en', 'oj', 'fr']:
   a. Fire BeforeSaveEvent (langcode=current).
3. Apply each save per §6.1 inside the transaction.
4. If any save fails: rollback all; raise PartialSaveException.
5. For each langcode: fire AfterSaveEvent.
6. Commit.
```

### 6.3 Load specific revision of a translation

```
$entity = $storage->load('teaching', 42);
$frTranslation = $entity->getTranslation('fr');
$historical = $frTranslation->loadRevision(7);

→ historical state of teaching 42 in French at vid=7.
→ Saving historical raises HistoricalRevisionWriteException.
```

---

## 7. Work package decomposition

Eight WPs.

| WP | Title | Primary FRs | Depends on |
|---|---|---|---|
| **WP01** | Schema design + migration template for sql-column two-axis (composite `(entity_id, langcode, vid)` revision table; extends `RevisionTableBuilder` whose current surrogate `vid` PRIMARY KEY is single-axis only) | FR-001..FR-006, FR-008 | M-006 translation substrate (shipped) |
| **WP02** | Schema design + migration template for sql-blob two-axis (extends `TranslationSchemaHandler` for per-revision blob rows) | FR-001, FR-003, FR-005, FR-008 | M-006 translation substrate (shipped) |
| **WP03** | Coordinator save semantics — extend `SaveContext` with `withTranslations(array)` builder (note: `withLangcode()` already exists from M-006); compose `RevisionableStorageDriver::writeRevision()` with langcode pinning | FR-009..FR-014 | WP01, WP02 |
| **WP04** | Coordinator load semantics — compose `TranslatableInterface::getTranslation()` (M-006) with `RevisionableEntityInterface::loadRevision()`; new `StorageMigrationException` and `EntityTranslationException::historicalRevisionWrite()` factory | FR-015..FR-019, FR-040..FR-042 | WP01, WP02 |
| **WP05** | Access policy composition + per-langcode policy method signatures (compose with M-006's existing `'translate'` operation) | FR-020..FR-024 | WP04 |
| **WP06** | Migration generator extensions — extend M-006's `AddTranslationsMigrationGenerator` for two-axis promotion; new `--add-revisions` flag; reverse migration | FR-025..FR-029 | WP01, WP02 |
| **WP07** | Two-axis listing integration — verify `Filter::langcode()` + `ListingCacheInvalidator` (both shipped by M-007) behave correctly on two-axis types; route filter resolver to per-`(entity, langcode)` current-revision pointer; integration tests | FR-030..FR-033, FR-033a, FR-014 | WP03, WP04 (M-007 listing pipeline already shipped) |
| **WP08** | Validation + documentation (Minoo teaching round-trip, canonical doctrine spec, cookbook, upgrade guide, charter cross-reference, surface-map sync) | FR-043..FR-048 | WP03..WP07 |

### 7.1 Sequencing diagram

```
M-006 translations (shipped) ──┬──► WP01 (sql-column schema) ──┐
M-007 listing-pipeline (shipped) │                              │
                               └──► WP02 (sql-blob schema) ─────┤
                                                                │
                                                ┌─── WP03 (save) ───┐
                                                │                   │
                                                ├─── WP04 (load) ───┤
                                                │                   │
                                                ├─── WP06 (gen) ────┤
                                                │                   │
                                                └─── WP05 (access) ─┤
                                                                    │
                                                              WP07 (listing) ──► WP08 (close)
```

### 7.2 Parallelizable WPs

After WP01 + WP02: WP03, WP04, WP05, WP06 can run in parallel. WP07 now depends only on WP03+WP04 (M-007 listing-pipeline already shipped). WP08 closes the mission.

### 7.3 Cross-mission dependencies

Both prior prerequisites have shipped (revalidated 2026-05-17):

- **M-006 `entity-storage-translations-v1`** — shipped 2026-05-13/14. Provides `TranslatableInterface`, `TranslatableEntityTrait`, `ContentEntityBase` composition, `FieldDefinition::translatable()`, `SaveContext::withLangcode()`, `AfterSaveEvent::affectedLangcodes()`, `EntityTranslationException` factories, `TranslationSchemaHandler`, and `AddTranslationsMigrationGenerator`. M-004 extends these for two-axis.
- **M-007 `listing-pipeline-v1`** — shipped 2026-05-16. Provides `Filter::langcode()`, `ListingCacheInvalidator` with langcode cache tags, `language.content` cache-context auto-injection. M-004 WP07 verifies and composes; does not redesign.
- **M-006 single-axis revisions substrate** — `RevisionableEntityInterface`, `RevisionableEntityTrait`, `RevisionableStorageDriver`, `RevisionTableBuilder`, `RevisionPruningPolicy`, `RevisionMetadata`, `RevisionableSqlBlobStorage` all shipped. M-004 extends `RevisionTableBuilder` from surrogate `vid` PK to composite `(entity_id, langcode, vid)` PK for two-axis entity types.

All 8 WPs are now plannable.

---

## 8. Acceptance criteria

The mission is complete when:

1. All 8 WPs are merged.
2. All FRs in §3 are covered by tests.
3. WP07's Minoo `teaching` end-to-end test passes in CI: 5 revisions across two languages with independent sequencing.
4. WP07's per-language access policy test passes: Coordinator sees English-only; Knowledge-Keeper sees both.
5. `docs/specs/entity-storage-two-axis.md` ships as canonical spec.
6. Charter §5.3 stable-surface entries gain the new exception types and SaveContext extensions, with tier (`stable`) and mission-status (`present`) labels on `public-surface-map.md` / `public-surface-map.php`.
7. The cookbook `docs/cookbook/translatable-revisionable-entities.md` ships, including performance guidance.

---

## 9. Open questions

Mission-specific, in addition to charter §11 operational items.

1. **Non-translatable field storage on translation revisions.** §5.1 stores non-translatable fields once on the entity-revision table; non-default-langcode revisions reference. Alternative: duplicate non-translatable values on every translation-revision row (simpler reads, more storage). Recommend: stored-once-with-reference. Read cost is one extra row lookup; storage cost reduction is substantial for entities with many languages and frequent translation edits.

2. **Pruning policy interactions.** A pruning policy defines per-language keep-counts. Should the policy be allowed to delete a default-langcode revision that's the only one referencing a particular non-translatable-field value? Recommend: no — pruning never deletes a default-langcode revision that's still referenced by any non-default-langcode current revision. Enforcement adds complexity.

3. **Multi-language save atomicity.** §6.2 says yes, single transaction. For very wide multi-language saves (10+ languages), the transaction could hold many locks. Recommend: stay atomic in v0.x; revisit if a real consumer hits the wide-save case.

4. **Default-langcode pointer redundancy.** `teaching.vid` (entity-level) duplicates `teaching__translation.vid` for `(tid, default_langcode)`. Same data, two places. Why both? Recommend: keep both. Entity-level pointer enables fast single-query loads of the "primary current revision" without joining the translation table. Sync is enforced by FR-008.

5. **Translation deletion as soft-delete?** §3.7 deletes hard. Some editorial workflows want "deprecated translation" semantics. Recommend: hard delete in v0.x; soft-delete via revision-with-status-field is an app concern.

6. **Listing pipeline cache invalidation granularity.** §3.6 emits both `entity:<type>:<id>` and `entity:<type>:<id>:<langcode>` tags. Saving any translation invalidates both. Alternative: emit only the langcode-scoped tag. Recommend: both — operators may have caches that depend on the non-translatable field values regardless of which translation was edited.

7. **`view_revision` policy method signature.** §3.4 says the policy receives the translation instance. A policy implementation can call `$translation->activeLangcode()` to discriminate. Recommend: provide a `$revision: RevisionableEntityInterface` parameter in the policy method too, so policies can introspect revision metadata (`revisionAuthor()`, `revisionCreatedAt()`) without a second lookup.

8. **Performance — revision count.** A 10-revision entity with 5 languages has 10 entity revisions + 50 translation revisions. Reasonable, but for high-edit-rate editorial sites this grows quickly. Recommend: cookbook discusses pruning as a near-mandatory operational practice for high-edit entities; ships disabled by default per FR-039.

---

## 10. References

- [ADR 017](../adr/017-per-field-translation.md) — governing decision (Accepted 2026-05-11), §"Revision × translation interaction" specifically.
- [ADR 016](../adr/016-revisions-first-class.md) — single-axis revisions; this mission composes them.
- [ADR 010](../adr/010-multi-backend-field-storage.md) — backend restriction (forbidden combinations).
- [ADR 011](../adr/011-entity-lifecycle-events.md) — lifecycle events fire per translation.
- [ADR 015](../adr/015-listing-pipeline-views-equivalent.md) — listing pipeline extended with langcode awareness.
- [`stability-charter.md`](stability-charter.md) §5.3 (governing surface).
- [`entity-storage-v2.md`](entity-storage-v2.md) — single-axis substrate; hard prerequisite.
- [`migration-platform-v1.md`](migration-platform-v1.md), [`config-management-v1.md`](config-management-v1.md) — sibling missions; independent of this one.
- [`drupal-comparison-matrix.md`](drupal-comparison-matrix.md) §1.11, §3.2 — origin of the gap.
- 2026-05-11 framework/app audit (`waaseyaa/minoo/docs/audits/2026-05-11-framework-app-audit.md`) — strategic context.
- Drupal prior art: Content Translation × Entity Revisions composition (the closest reference for per-(entity, langcode) revisions).
- Minoo milestone: #21 Anishinaabemowin Localization — the canonical consumer use case driving this mission.

---

## 12. Revalidation 2026-05-17 (post M-006 + M-007 substrate ship)

This section captures the audit findings from re-validating §3 FRs and §7 WPs against the actual code that landed in M-006 (2026-05-13/14) and M-007 (2026-05-16). Filed as required by the original 2026-05-12 "Unblocker" caveat.

### 12.1 M-006 substrate audit

Shipped surfaces relevant to M-004:

- `Waaseyaa\Entity\TranslatableInterface` — full per-langcode API (`defaultLangcode()`, `activeLangcode()`, `getTranslation()`, `addTranslation()`, `removeTranslation()`, `translations()`, `getTranslationLanguages()`).
- `Waaseyaa\Entity\TranslatableEntityTrait` — default implementation; `Waaseyaa\Entity\ContentEntityBase` composes it.
- `Waaseyaa\Entity\Exception\EntityTranslationException` — unified domain exception with static factories (`translationNotFound`, `cannotRemoveDefault`, `notTranslatable`, `translationAlreadyExists`, `langcodeRequired`). **No separate `TranslationNotFoundException` / `DefaultLangcodeRemovalException` classes.**
- `Waaseyaa\Field\FieldDefinition::translatable()` + `isTranslatable()` — per-field flag.
- `Waaseyaa\EntityStorage\Schema\TranslationSchemaHandler` — handles both sql-blob and sql-column translation tables; `sync()`, `translationTableName()`, `multiCardinalityTableName()`, `partitionTranslatableFields()`.
- `Waaseyaa\EntityStorage\SaveContext` — has `?string $langcode` field and `withLangcode($langcode)` builder. **Does not have `withTranslations(array)` builder yet.**
- `Waaseyaa\EntityStorage\Event\AfterSaveEvent::affectedLangcodes()` — already returns `list<string>|null` for multi-language saves.
- `Waaseyaa\Cli\Handler\AddTranslationsMigrationGenerator` + `MissingLangcodeColumnException` — `make:migration --add-translations` flag for revisionable-only → translatable promotion (single-axis only today).

Findings driving FR/WP edits:

- **FR-040 must reconcile** with M-006's unified-exception pattern. M-004's original "five exception classes" plan conflicts with established naming. Updated to add factories to the existing `EntityTranslationException` and to ship a single new `StorageMigrationException` for migration-generator errors.
- **FR-014** can lean on `AfterSaveEvent::affectedLangcodes()` unchanged for cache-invalidation propagation; updated to state this explicitly.
- **WP03** scope clarified: only `withTranslations(array)` is new on `SaveContext`; `withLangcode` already exists.
- **FR-025 / WP06** scope shrank: `--add-translations` exists; this mission extends it to two-axis promotion and adds `--add-revisions`.

### 12.2 M-007 substrate audit

Shipped surfaces relevant to M-004:

- `Waaseyaa\Listing\Filter::langcode($code)` — canonical langcode filter factory.
- `Waaseyaa\Listing\ListingCacheInvalidator` — emits `entity:<type>:<id>:<langcode>` tags from `AfterSaveEvent::affectedLangcodes()`; falls back to `activeLangcode()` for single-langcode saves; **also emits the langcode-less `entity:<type>:<id>` tag in both cases** (verified in source).
- `Waaseyaa\Listing\ListingDefinition` — auto-injects `language.content` cache context when the entity type is translatable.
- `FilterDefinition`, `ExposedFilterParser`, `ListingResolver`, `ListingResult`, `Pagination`, `Sort`, `SortDefinition`, `Operator`, `ListingDefinitionValidator`, `ListingCacheKeyBuilder`, `ExposedFilterCoercer`, `ExposedFilterValues`, `EntityRepositoryRegistry`, `ListingDiscoverer`, `HasListingsInterface`, `ServiceProvider` — full pipeline shipped.

Findings driving FR/WP edits:

- **FR-030 reframed**: original spec proposed a new `ListingDefinition::langcode` value-object field. M-007 ships the canonical `Filter::langcode()` factory instead — M-004 MUST use it, not introduce a parallel field-level API.
- **FR-032 simplified**: the cache-tag emission contract already exists in M-007; M-004 WP07 only verifies it behaves correctly when invoked from a two-axis save (i.e., that `affectedLangcodes()` is correctly populated by the per-`(entity, langcode)` revision writer).
- **FR-033 renamed** from `language.requested` to `language.content` to match M-007's canonical token.
- **FR-033a added** to capture the genuinely new contract: filter resolver must read each result entity at the langcode's current revision, not at the entity-level primary current revision.
- **WP07 scope shrank from "design + build" to "verify + integrate"** — the substrate is in place; M-004 verifies two-axis save events fire the right `affectedLangcodes` and the langcode filter routes through the per-`(entity, langcode)` current-revision pointer.

### 12.3 Other findings

- `RevisionTableBuilder` in `packages/entity-storage/src/Schema/` currently uses surrogate `vid INTEGER PRIMARY KEY`. M-004 WP01 MUST extend it (or fork a `TwoAxisRevisionTableBuilder`) to support the composite `(entity_id, langcode, vid)` PK for two-axis types. Single-axis types retain the surrogate PK — backward compatible.
- `RevisionableStorageDriver` exposes `writeRevision($entityId, $values, ?$log)`, `updateRevision`, `readRevision`, `readMultipleRevisions`, `getLatestRevisionId`, `getRevisionIds`, `deleteRevision`, `deleteAllRevisions`. M-004 WP03 extends the read/write signatures to accept an optional `?string $langcode` so per-`(entity, langcode)` storage is dispatchable.
- `RevisionableEntityInterface` has `revisionId()`, `isCurrentRevision()`, `revisionMetadata()`. M-004 needs no signature changes here; composition is via `getTranslation()->revisionId()`.
- `ContentEntityBase implements TranslatableInterface` already; a two-axis entity additionally implements `RevisionableEntityInterface` and uses `RevisionableEntityTrait` (see the trait's class docblock: `class Teaching extends ContentEntityBase implements RevisionableEntityInterface { use RevisionableEntityTrait; }`). Composition shape verified.

### 12.4 FR delta summary

| Change kind | FRs |
|---|---|
| Renumbered / unchanged | FR-001..FR-013, FR-015, FR-018..FR-024, FR-026..FR-029, FR-031, FR-034, FR-036..FR-039, FR-043..FR-048 |
| Edited to reference shipped substrate | FR-014, FR-016, FR-017, FR-025, FR-030, FR-032, FR-033, FR-035 |
| New | FR-033a (filter resolver reads at langcode current revision) |
| Reframed | FR-040, FR-041, FR-042 (consolidated to factory-on-`EntityTranslationException` + single new `StorageMigrationException`; five-class plan dropped) |
| Removed | None |

### 12.5 WP delta summary

Final WP count remains **8**.

| WP | Change |
|---|---|
| WP01 | Title clarified — extends single-axis `RevisionTableBuilder` to composite PK |
| WP02 | Title clarified — extends M-006 `TranslationSchemaHandler` for per-revision blob rows |
| WP03 | Scope clarified — only `withTranslations(array)` is new (M-006 already shipped `withLangcode`) |
| WP04 | Scope expanded — owns FR-040..FR-042 (new exception class + factory) |
| WP05 | Unchanged scope; called out composition with M-006's `'translate'` operation |
| WP06 | Scope shrank — extend M-006's existing `AddTranslationsMigrationGenerator`; add `--add-revisions` |
| WP07 | Scope shrank from design+build to verify+integrate (M-007 substrate already there); now depends only on WP03+WP04, not on a listing-pipeline mission |
| WP08 | Unchanged scope |

### 12.6 Recommended dispatch order for tasks phase

1. **WP01 + WP02** (parallel) — schema substrate must land first.
2. **WP03 + WP04 + WP06** (parallel after WP01+WP02) — save semantics, load semantics, migration generator.
3. **WP05** — access policy composition (depends on WP04's load semantics).
4. **WP07** — listing integration (depends on WP03 for save-event correctness and WP04 for read-at-langcode-revision).
5. **WP08** — close-out validation and docs.

### 12.7 Ambiguities flagged for plan phase

- §9 Q1 (non-translatable field storage strategy) is recommended but not normative — plan phase should commit to "stored once on default-langcode revision" per the recommendation and update FR-004/FR-005 if anything changes.
- §9 Q3 wide multi-language save atomicity remains advisory; no FR change needed.
- §9 Q7 policy method signature (passing `?RevisionableEntityInterface $revision`) should be settled in WP05's plan.

---

## 11. Mission metadata for Spec Kitty

```yaml
mission:
  id: TBD
  title: Entity Storage — Translatable + Revisionable Two-Axis Interaction
  status: draft-spec
  governing_adrs: [016, 017]
  related_adrs: [010, 011, 015]
  charter_dependencies:
    - section: §5.3
      relation: governs
  external_dependencies:
    - mission: entity-storage-v2
      relation: hard-prerequisite
      gates_wp: WP01-WP08 (entire mission)
    - mission: listing-pipeline-v1 (TBD)
      relation: required-for-wp07
  validation_consumer: minoo
  validation_entity_type: teaching
  work_packages: 8
  parallelizable_after_wp02: true
  estimated_breaking_change_count: 0  # additive surface; existing single-axis types unchanged
  ships_followup_mission_unblocked: none (workflow / moderation is a separate future ADR)
  agent_assignments:
    implementer: sonnet
    reviewer: opus
```
