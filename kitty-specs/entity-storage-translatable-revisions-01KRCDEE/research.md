# Phase 0 Research — M-004 Entity Storage Translatable + Revisionable

**Date:** 2026-05-16
**Spec:** [`spec.md`](spec.md) (revalidated 2026-05-17, commit `de5c6eba6`)
**Plan:** [`plan.md`](plan.md)

This document records the decisions reached during plan Phase 0. Each research item resolves either an architectural choice that affects the Phase 1 design (`data-model.md`, `contracts/`) or an open question carried from the spec's §9.

---

## R-01 Composite PK strategy

**Decision.** Use composite `(tid, langcode, vid)` uniqueness on the translation-revision table; retain `vid INTEGER PRIMARY KEY` as the surrogate primary key for query simplicity. The default-langcode entity-revision table uses surrogate `vid INTEGER PRIMARY KEY` unchanged from single-axis revisions.

**Shape (sql-column, `teaching`):**

```
teaching__translation__revision(
  vid INTEGER PRIMARY KEY,    -- monotonic surrogate; enables cross-langcode interleaved ordering
  tid INTEGER NOT NULL,        -- FK to teaching.tid
  langcode TEXT NOT NULL,
  revision_created_at TEXT,
  revision_author INTEGER,
  revision_log TEXT,
  -- translatable field columns
  UNIQUE (tid, langcode, vid)  -- composite uniqueness expresses the logical PK
)
CREATE INDEX idx_teaching_tx_rev_lookup ON teaching__translation__revision (tid, langcode, vid DESC);
```

**Why surrogate + composite uniqueness instead of pure composite PK.**

1. `listRevisions()` without a langcode argument (FR-018) returns revisions of ALL langcodes interleaved by creation order. A surrogate monotonic `vid` makes that ordering O(log n) via the PK btree; pure composite PK requires `ORDER BY revision_created_at DESC` over the full set.
2. `loadRevision(7)` (FR-017) takes a single `vid`. Surrogate PK keeps the API ergonomic — callers don't need to pass `(tid, langcode, vid)` triples.
3. Composite uniqueness enforces correctness (one row per `(tid, langcode, vid)` combination) without sacrificing query patterns.

**Default-langcode anchor.** `teaching.vid` (entity-level current revision pointer) duplicates `teaching__translation.vid` for `(tid, default_langcode)`. FR-008 enforces the invariant; `RevisionableStorageDriver` updates both in the same transaction. Resolves §9 Q4.

---

## R-02 RevisionTableBuilder extension vs fork

**Decision.** EXTEND `Waaseyaa\EntityStorage\Schema\RevisionTableBuilder`. Add a per-call parameter (or constructor flag) that toggles composite-PK emission when the entity type is both translatable + revisionable. Default path (single-axis) emits SQL byte-for-byte identical to M-006.

**Why not fork (`TwoAxisRevisionTableBuilder`).**

- The divergence is small: one extra `langcode TEXT NOT NULL` column + one extra `UNIQUE (tid, langcode, vid)` constraint + index variation.
- A fork creates two parallel code paths drifting over time; an extension keeps single-axis and two-axis under one maintenance umbrella.
- M-006 shipped `RevisionTableBuilder` as the canonical builder — consumers know its name.

**Backward-compat guarantee.** WP01 acceptance includes a contract test asserting M-006's single-axis revision table SQL is byte-for-byte unchanged. Single-axis revisionable-only types and single-axis translatable-only types are not touched.

---

## R-03 Non-translatable field storage strategy (resolves §9 Q1)

**Decision.** Non-translatable fields are **stored once on the default-langcode entity-revision row**. Other-langcode revisions reference via single-step fallback to the default-langcode current-revision.

This is the recommendation in §9 Q1, now normative.

**Read path.**

```
read(entity=42, langcode='oj') {
  // 1. Look up the oj current-revision pointer:
  $oj_vid = SELECT vid FROM teaching__translation WHERE tid=42 AND langcode='oj';
  // 2. Read translatable fields at that revision:
  $oj_row = SELECT * FROM teaching__translation__revision WHERE vid=$oj_vid;
  // 3. Single-step fallback for non-translatable fields:
  $default_vid = SELECT vid FROM teaching WHERE tid=42;   -- default-langcode current-revision
  $non_trans_row = SELECT * FROM teaching__revision WHERE vid=$default_vid;
  // 4. Merge translatable (from $oj_row) + non-translatable (from $non_trans_row).
}
```

**Why stored-once.**

- **Storage**: a 5-language × 50-revision entity stores non-translatable fields in 50 rows (one per default-langcode revision), not 250 (one per `(langcode, vid)` cell).
- **Editorial integrity**: changing a non-translatable field (`community_id`) creates one new default-langcode revision; doesn't fragment translation revision history.
- **Read cost**: one extra row lookup per non-default-langcode read. NFR-A acceptable for v0.x.

**Trade-off documented in cookbook (FR-046)**: high read-rate non-default-langcode listings pay the extra lookup; admins may shard caches by langcode.

---

## R-04 SaveContext::withTranslations(array) shape

**Decision.** Add a single new builder to `Waaseyaa\EntityStorage\SaveContext`:

```php
public function withTranslations(array $langcodes): self
```

- `$langcodes` is a non-empty list of strings; empty array raises `\InvalidArgumentException`.
- Returns a new immutable instance carrying `?list<string> $translations`.
- Mutually exclusive with `withLangcode()` at call site: when `$translations` is non-null, the coordinator iterates over it inside a transaction (per §6.2) and ignores `$langcode`. The `withLangcode()` builder remains for single-langcode saves.
- `default()` returns `$translations: null` (no multi-language save).

`AfterSaveEvent::affectedLangcodes()` already returns `list<string>|null` (M-006) — the storage coordinator populates it from `$translations` when multi-language; from `[$langcode]` when single-langcode; null otherwise (M-006 behavior unchanged).

---

## R-05 Load semantics — revision × langcode lookup

**Decision.** Compose at the interface level; no new top-level interface.

| API call | Returns |
|---|---|
| `$storage->load($type, $id)` | Entity with default-langcode current revision active. |
| `$entity->getTranslation($langcode)` | Same entity instance with `activeLangcode` switched + translatable field values reloaded from the langcode's current-revision row. |
| `$entity->getTranslation($lc)->loadRevision($vid)` | Historical translation instance; `isCurrentRevision()` false. |
| `$entity->loadRevision($vid)` | Historical default-langcode entity at vid (existing M-006 single-axis behavior; two-axis unchanged for the default-langcode path). |
| `$entity->listRevisions(?$langcode = null)` | All revisions interleaved by creation order if `$langcode` null; scoped to one langcode otherwise (FR-018). |
| `$entity->translations()` | Langcodes with at least one revision (FR-019). |

**Historical write guard.** `loadRevision()` flags the returned instance as historical. Calling `save()` on a historical instance raises `EntityTranslationException::historicalRevisionWrite($vid, $langcode)`. The factory is new on M-006's exception class (FR-040).

---

## R-06 Exception consolidation (resolves FR-040..042 final shape)

**Decision.** Follow M-006's unified-exception pattern strictly. The original spec's "five exception classes" plan is dropped.

| Need | Resolution |
|---|---|
| Historical-revision write attempt (FR-017) | Add factory `EntityTranslationException::historicalRevisionWrite($vid, $langcode)`. Code: `'historical_revision_write'`. |
| Translation not found (FR-016) | Reuse existing `EntityTranslationException::translationNotFound($langcode)`. M-006 already ships. |
| Default-langcode removal attempt (FR-035) | Reuse existing `EntityTranslationException::cannotRemoveDefault($langcode)`. M-006 already ships. |
| Migration generator already-two-axis (FR-029) | New class `Waaseyaa\EntityStorage\Exception\StorageMigrationException::noOpPromotion($entityType)`. Code: `'no_op_promotion'`. |
| Unsupported backend for translatable field (FR-006) | New class `Waaseyaa\EntityStorage\Exception\StorageMigrationException::unsupportedTwoAxisField($fieldName, $backend)`. Code: `'unsupported_two_axis_field'`. |

**Classes dropped from original plan:** `HistoricalRevisionWriteException`, `TranslationNotFoundException`, `DefaultLangcodeRemovalException`, `UnsupportedTwoAxisFieldException`, `NoOpMigrationException`. Consolidated into the two classes above with factory methods.

**Stability semantics (FR-041, FR-042).** Each factory sets a stable string `code` (M-006 convention). Class names and `code` strings are stable surface; renames follow charter §4 deprecation cycle.

---

## R-07 Access policy composition — `view_revision` signature (resolves §9 Q7)

**Decision.** Pass an optional `?RevisionableEntityInterface $revision = null` parameter to policy methods.

**Signature shape (illustrative, not normative).**

```php
public function access(
    EntityInterface $entity,
    AccountInterface $account,
    string $operation,
    ?RevisionableEntityInterface $revision = null,
): AccessResult
```

- For `view`, `edit`, `delete`, `translate` operations: `$revision` is null (existing single-axis behavior unchanged).
- For `view_revision` operation: `$revision` is the specific revision the policy is being consulted about (so `$revision->revisionAuthor()`, `revisionCreatedAt()`, `revisionLog()` are introspectable without a second storage lookup).
- For two-axis types, the `$entity` parameter is the **translation instance** (so `$entity->activeLangcode()` discriminates per-language) and `$revision` is the historical revision instance.

**Fallback rules** (FR-021, FR-022):

- `view_revision` not declared → falls back to `view` (with `$revision` argument dropped — `view` doesn't take it).
- `translate` not declared → falls back to `edit`.

**Why optional parameter not new method.** New operation `view_translation_revision` was explicitly rejected (FR-023). Composition by langcode introspection + optional revision argument is cleaner and avoids combinatorial explosion of operation names.

---

## R-08 Listing pipeline integration — M-007 substrate is sufficient

**Decision.** Consume M-007 unchanged for the canonical user-facing surface. Add **one** new component to route langcode filtering through the per-`(entity, langcode)` current-revision pointer.

**M-007 surface consumed.**

- `Waaseyaa\Listing\Filter::langcode($code)` — canonical factory; FR-030 mandates this is the only user-facing API.
- `Waaseyaa\Listing\ListingCacheInvalidator` — emits `entity:<type>:<id>` + `entity:<type>:<id>:<langcode>` tags driven by `AfterSaveEvent::affectedLangcodes()`. FR-032 verified.
- `ListingDefinition` auto-injects `language.content` cache context when the entity type is translatable. FR-033 verified.

**New component (FR-033a).** `Waaseyaa\EntityStorage\Listing\TwoAxisFilterResolver` (or equivalent hook in M-007's existing resolver, decided at implementation): when a `Filter::langcode('oj')` is applied to a two-axis entity type, the resolver routes each result entity's read through the langcode's current-revision pointer (`teaching__translation.vid`) instead of the entity-level primary current-revision (`teaching.vid`). For single-axis translatable-only types (M-006), the langcode column on `teaching__translation` is the read target unchanged.

**No `ListingDefinition::langcode` value-object field.** The spec's earlier proposal is dropped per FR-030. `Filter::langcode()` is the canonical surface.

---

## R-09 Migration generator — extend `AddTranslationsMigrationGenerator` + new sibling

**Decision.** Two-axis promotion has two input shapes; treat them in two generators.

| Input shape | Flag | Generator | New / Extend |
|---|---|---|---|
| non-translatable + non-revisionable → translatable | `--add-translations` | `AddTranslationsMigrationGenerator` (M-006) | unchanged |
| revisionable + non-translatable → revisionable + translatable | `--add-translations` | `AddTranslationsMigrationGenerator` (M-006) | EXTEND (new branch detecting revisionable target → emits two-axis template) |
| translatable + non-revisionable → revisionable + translatable | `--add-revisions` | `AddRevisionsMigrationGenerator` | NEW (FR-025 explicit) |
| revisionable + translatable + asked to promote | (any) | (any) | raises `StorageMigrationException::noOpPromotion` (FR-029) |

**Why a new generator for `--add-revisions` and not a third branch.**

- `AddTranslationsMigrationGenerator` already detects "is the target translatable?" to no-op. Adding "is the target revisionable?" branching is straightforward — one new emit path for revisionable → two-axis.
- The translatable-only → two-axis case (adding revisions to a translatable-only type) requires generating an entirely different SQL shape: backfilling the current translation rows as initial per-langcode revisions, adding the per-langcode current-revision pointer column on the translation table, creating the parallel translation-revision table. This is more code; a sibling generator keeps the branching simple.

**Reverse migrations.** Both promotions are reversible by default (FR-028); reverse migration loses revision history for non-current revisions (documented in the generated migration's docblock). M-006's reverse-migration template is reused.

---

## Summary of decisions

| ID | Decision | Affected FRs / WPs |
|---|---|---|
| R-01 | Composite `(tid, langcode, vid)` uniqueness + surrogate `vid` PK | FR-001, FR-008; WP01, WP02 |
| R-02 | Extend `RevisionTableBuilder`, not fork | WP01 |
| R-03 | Non-translatable fields stored once on default-langcode revision | FR-004, FR-005; resolves §9 Q1; WP01, WP02 |
| R-04 | `SaveContext::withTranslations(array)` builder; mutually exclusive with `withLangcode()` | FR-012, FR-013; WP03 |
| R-05 | Interface-level composition for load semantics | FR-015..FR-019; WP04 |
| R-06 | One new exception class + one new factory; consolidate; M-006 pattern | FR-040..FR-042; WP04 |
| R-07 | `?RevisionableEntityInterface $revision = null` policy parameter | FR-020..FR-024; resolves §9 Q7; WP05 |
| R-08 | Consume M-007; add one new component for FR-033a | FR-030..FR-033a; WP07 |
| R-09 | Extend `AddTranslationsMigrationGenerator` + new sibling `AddRevisionsMigrationGenerator` | FR-025..FR-029; WP06 |
