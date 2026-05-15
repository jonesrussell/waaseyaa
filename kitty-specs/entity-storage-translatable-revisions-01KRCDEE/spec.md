<!-- Canonical doctrine spec: docs/specs/entity-storage-translatable-revisions.md -->
<!-- Mission metadata: docs/specs/missions/M-004-entity-storage-translatable-revisions/mission.json -->
<!-- Mission ID: M-004 | Spec Kitty mission_id below in meta.json -->

> **⚠️ PARTIALLY UNBLOCKED — DO NOT PLAN YET (2026-05-14, updated post-M-006 close-out)**
>
> Original 2026-05-12 BLOCKED stamp identified two hard prerequisites. Status today:
>
> 1. ~~**Single-axis translation substrate.**~~ ✅ **SATISFIED** by M-006 (`entity-storage-translations-v1-01KRF0FQ`, squash `0f7e1809a` on 2026-05-13, mission closed in PR #1485 / `a7840a36a` on 2026-05-14). The translation tombstone is gone: `TranslatableInterface` (`packages/entity/src/TranslatableInterface.php`) and `TranslatableEntityTrait` are in place; per-field `FieldDefinition::translatable()` works in both `sql-blob` and `sql-column` backends via `TranslationSchemaHandler`; `SaveContext::langcode` + `withLangcode()` carry per-langcode save semantics.
> 2. **ADR 015 listing pipeline.** ❌ Still pending. Required for WP07 (per-langcode listing filters, langcode cache tags). Only `docs/adr/015-listing-pipeline-views-equivalent.md` exists; no `listing-pipeline-v1` mission specced, no implementation in `packages/`.
>
> **Plannable today:** WP01..WP06 (per §7.2 below, after WP01+WP02 the parallel branch WP03/WP04/WP05/WP06 has no listing-pipeline dependency).
> **Still gated:** WP07 (needs `listing-pipeline-v1` to spec and ship) and WP08 (closes the mission; transitively gated).
>
> **Unblocker (full):** Spec and ship `listing-pipeline-v1` as a separate mission. Before *any* planning begins, the original author's caveat still holds — revisit §3 FRs and §7 WP decomposition against the M-006 substrate that actually shipped; the decomposition may shift now that translation deliverables are concrete code instead of a planned shape.

# Entity Storage — Translatable + Revisionable Two-Axis Interaction

**Status:** Draft mission spec (2026-05-11), **PARTIALLY UNBLOCKED 2026-05-14** (prereq 1 M-006 satisfied 2026-05-13 squash / 2026-05-14 close-out; prereq 2 `listing-pipeline-v1` still pending; spec still needs revalidation against the M-006 substrate before planning)
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
- **FR-014** Lifecycle events (`BeforeSaveEvent` / `AfterSaveEvent`, per ADR 011) MUST fire per saved translation. A multi-language save firing four translations fires four pairs of events. Each event carries the saved langcode in `SaveContext`.

### 3.3 Load semantics

- **FR-015** `$storage->load($entityType, $id)` MUST return the entity with its default-langcode current revision active.
- **FR-016** `$entity->getTranslation($langcode)` MUST return the entity with that langcode's current revision active. If no translation exists, raise `TranslationNotFoundException` (per ADR 017's stable surface).
- **FR-017** `$entity->getTranslation($langcode)->loadRevision($vid)` MUST return a specific revision of that translation. The returned entity is in a "historical" state; saves on it are forbidden (raise `HistoricalRevisionWriteException`).
- **FR-018** `$entity->listRevisions()` MUST return revisions of ALL languages in interleaved descending-creation order. `$entity->listRevisions($langcode)` scopes to one language.
- **FR-019** `$entity->translations()` MUST return langcodes that have at least one revision. Languages with translations that have been fully purged via pruning MUST NOT appear.

### 3.4 Access policy composition

- **FR-020** `view_revision` and `translate` access operations apply to the translation instance, not to the language-agnostic entity. A policy's method receives the translation instance and may introspect `$entity->activeLangcode()` for per-language access decisions.
- **FR-021** Policies that do NOT declare `view_revision` MUST fall back to `view` per ADR 016 FR-040. Same fallback applies for translations: `view_revision` on the French translation falls back to `view` on the French translation.
- **FR-022** Policies that do NOT declare `translate` MUST fall back to `edit` per ADR 017 §"Translation operation." Same fallback applies for revisions on translations.
- **FR-023** The framework MUST NOT add a new `view_translation_revision` operation. The composition of `view_revision` + langcode introspection is sufficient and clearer.
- **FR-024** Worked example (Minoo): a policy may grant `view_revision` on the English revision to Coordinators but require Knowledge-Keeper role for `view_revision` on the Anishinaabemowin revision. The policy method tests `$entity->activeLangcode()` and applies different role checks. No new operation needed.

### 3.5 Migration generator extensions

- **FR-025** `bin/waaseyaa make:storage-migration <entity_type>` MUST gain two new flags:
  - `--add-translations` — adds translation support to a revisionable-only type.
  - `--add-revisions` — adds revision support to a translatable-only type.
- **FR-026** When promoting **revisionable-only → two-axis**: the migration creates the translation tables, backfills existing revisions as default-langcode revisions, sets per-(entity, langcode) current-revision pointers for the existing default-langcode revision.
- **FR-027** When promoting **translatable-only → two-axis**: the migration adds `vid` to the existing translation tables, creates a parallel translation-revision table, backfills the current translation values as initial revisions.
- **FR-028** Both promotions MUST be reversible by default. Reverse migration loses revision history for non-current revisions (documented in the migration file's docblock).
- **FR-029** Promoting an entity type that is already two-axis MUST fail with `NoOpMigrationException`.

### 3.6 Listing pipeline integration (ADR 015)

- **FR-030** `ListingDefinition` MUST gain an optional `langcode` field. When set, the listing returns the current revision of that langcode for each result entity. When unset, defaults to the request's active langcode or the site default.
- **FR-031** A listing of `teaching` entities filtered by `langcode: 'oj'` MUST return only entities with an Anishinaabemowin translation. Entities without that translation are excluded.
- **FR-032** Cache tags for two-axis listings MUST include the langcode: `entity:teaching:42:oj`. Tags without langcode (`entity:teaching:42`) MUST also be emitted; saving any translation of entity 42 invalidates both.
- **FR-033** Cache contexts MUST include `language.requested` when the listing is langcode-aware. Listings with no langcode filter still depend on language context if non-translatable fields are surfaced.

### 3.7 Translation deletion

- **FR-034** `$entity->removeTranslation($langcode)` MUST delete the (entity, langcode) row and all its revisions. The translation is unrecoverable.
- **FR-035** Attempting to remove the default-langcode translation MUST raise `DefaultLangcodeRemovalException`. To remove the default-langcode "translation," operators delete the whole entity (`$storage->delete([$entity])`).
- **FR-036** Removing a non-default translation MUST NOT affect other-language revisions or the entity itself.

### 3.8 Revision pruning extension

- **FR-037** Revision pruning policies (from ADR 016 `RevisionPruner`) MUST be extensible per-language. A `PruningPolicy` on a two-axis type MAY apply different keep-counts to different languages.
- **FR-038** Pruning MUST NEVER delete the current revision of any language.
- **FR-039** Pruning MUST be a no-op by default. Operators opt in per entity type with explicit configuration.

### 3.9 Error model

- **FR-040** The mission MUST ship these exception types on stable surface: `TranslationNotFoundException`, `HistoricalRevisionWriteException`, `DefaultLangcodeRemovalException`, `NoOpMigrationException`, `UnsupportedTwoAxisFieldException`.
- **FR-041** Each carries a stable string `code` field per charter §4.4.
- **FR-042** Renames or removals of any of these types follow the deprecation cycle (charter §4).

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
| `TranslationNotFoundException`, `HistoricalRevisionWriteException`, `DefaultLangcodeRemovalException`, `NoOpMigrationException`, `UnsupportedTwoAxisFieldException` | Exception classes | New on stable surface |
| `bin/waaseyaa make:storage-migration --add-translations / --add-revisions` flags | CLI flags | Extension of entity-storage-v2 generator |
| `ListingDefinition::langcode` field | Value-object extension | Extension of ADR 015 surface |
| Cache-tag format `entity:<type>:<id>:<langcode>` | Tag string convention | Stable surface |

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
| **WP01** | Schema design + migration template for sql-column two-axis | FR-001..FR-006, FR-008 | entity-storage-v2 complete |
| **WP02** | Schema design + migration template for sql-blob two-axis | FR-001, FR-003, FR-005, FR-008 | entity-storage-v2 complete |
| **WP03** | Coordinator save semantics (per-translation revision creation, SaveContext extension) | FR-009..FR-014 | WP01, WP02 |
| **WP04** | Coordinator load semantics (getTranslation × loadRevision composition) | FR-015..FR-019 | WP01, WP02 |
| **WP05** | Access policy composition + per-langcode policy method signatures | FR-020..FR-024 | WP04 |
| **WP06** | Migration generator extensions (`--add-translations`, `--add-revisions`, reverse) | FR-025..FR-029 | WP01, WP02 |
| **WP07** | Listing pipeline + lifecycle event integration (per-langcode filters, cache tags, event payload) | FR-030..FR-033, FR-014 | WP03, WP04, ADR 015 listing pipeline shipping |
| **WP08** | Validation + documentation (Minoo teaching round-trip, spec, cookbook, upgrade guide) | FR-043..FR-048 | WP03..WP07 |

### 7.1 Sequencing diagram

```
entity-storage-v2 complete ──┬──► WP01 (sql-column schema) ──┐
                             │                                │
                             └──► WP02 (sql-blob schema) ─────┤
                                                              │
                                              ┌───── WP03 (save) ──┐
                                              │                    │
                                              ├───── WP04 (load) ──┤
                                              │                    │
                                              ├───── WP06 (gen) ───┤
                                              │                    │
                                              └─── WP05 (access) ──┤
                                                                   │
                                  ADR 015 listing pipeline ────► WP07 ──► WP08 (close)
```

### 7.2 Parallelizable WPs

After WP01 + WP02: WP03, WP04, WP05, WP06 can run in parallel. WP07 needs ADR 015's listing pipeline shipping. WP08 closes the mission.

### 7.3 Cross-mission dependencies

This mission is the **most dependency-blocked** of the four:

- **entity-storage-v2 single-axis revisions** — required for FR-001..FR-008. Schema design layers on top of single-axis revision schema.
- **entity-storage-v2 single-axis translations** — required for FR-009..FR-014. Save/load composition layers on single-axis translation surface.
- **ADR 015 listing pipeline** — required for WP07. Per-langcode listing filtering is a listing-pipeline extension.
- **Migration generator** (entity-storage-v2 WP10) — required for WP06.

WPs 01–05 can begin after entity-storage-v2's revision and translation WPs ship. WP07 cannot begin until listing-pipeline implementation lands (separate ADR 015 mission, not yet specced).

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
