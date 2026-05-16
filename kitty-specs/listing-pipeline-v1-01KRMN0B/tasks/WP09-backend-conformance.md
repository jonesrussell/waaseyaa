---
work_package_id: WP09
title: Backend conformance tests — SqlColumn / SqlBlob / non-translatable listing scenarios
dependencies:
- WP05
- WP07
requirement_refs:
- FR-023
- FR-046
- FR-047
- FR-048
- NFR-005
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T043
- T044
- T045
- T046
history: []
authoritative_surface: packages/listing/
execution_mode: code_change
owned_files:
- packages/listing/tests/Backend/SqlColumnTranslatableListingTest.php
- packages/listing/tests/Backend/SqlBlobTranslatableListingTest.php
- packages/listing/tests/Backend/NonTranslatableListingTest.php
- kitty-specs/listing-pipeline-v1-01KRMN0B/contracts/conformance-matrix.md
tags: []
agent: "claude:sonnet:python-implementer:implementer"
shell_pid: "37781"
---

## Objective

Prove `ListingResolver` behaves correctly across the two SQL backends shipped by M-001 + M-006 (`sql-column` and `sql-blob`) plus the non-translatable case. These tests live separately from `ListingResolverContract` (WP05) because they're backend-specific scenarios that don't fit the abstract-contract pattern.

## Context

- `sql-column` translation handling joins to `<table>__translation` sibling tables (M-006 shipped).
- `sql-blob` translation handling probes the `_data` JSON column's translation map via JSON path expressions.
- Non-translatable entity types should NOT add `language.content` to `cacheContexts` and NOT emit `langcode` cache tags.
- Refer to M-006's existing backend conformance tests in `packages/entity-storage/tests/Backend/` for the canonical test-shape pattern.

## Subtask details

### T043 — `SqlColumnTranslatableListingTest`

**Purpose:** Test that listings over sql-column translatable entity types correctly join + filter by langcode + emit per-langcode cache tags.

**Steps:**
1. Create `packages/listing/tests/Backend/SqlColumnTranslatableListingTest.php`:
   - Set up:
     - `DBALDatabase::createSqlite(':memory:')`
     - Register a fixture translatable entity type with sql-column backend (mirror M-006's `TranslatableArticleFixture` pattern; ensure PSR-4-compliant file per the M-006 lesson)
     - Seed fixture entities with translations in `['en', 'mi-tle', 'fr']`
     - Construct `ListingResolver` with the SQL-backed `EntityRepositoryRegistry`
   - Test cases:
     - `listingFiltersByLangcodeExplicit` — `Filter::langcode('en')` returns only entities with English translations
     - `listingFiltersByLangcodeImplicit` — no langcode filter; resolver auto-applies request langcode
     - `cacheTagsIncludeLangcodePerRow` — assert tags include `entity:translatable_article:42:en`
     - `cacheContextsIncludeLanguageContent` — assert `'language.content'` in `cacheContexts`
     - `sortFieldOnTranslationTableJoinedCorrectly` — sort by `title` (translatable field) returns rows in expected order
     - `untranslatedFieldUsesPrimaryTable` — non-translatable field is queried against primary table

**Files:** Test (~200 lines).

### T044 — `SqlBlobTranslatableListingTest`

**Purpose:** Same scenarios as T043 but exercising sql-blob backend's JSON-path-probe translation handling.

**Steps:**
1. Create `packages/listing/tests/Backend/SqlBlobTranslatableListingTest.php`:
   - Mirror T043's test setup with sql-blob backend
   - Same case names; assertions adapted for sql-blob storage semantics
   - Verify SQLite JSON1 extension support for the path expression (test setup may need to verify SQLite version or skip on unsupported)

**Files:** Test (~200 lines).

### T045 — `NonTranslatableListingTest`

**Purpose:** Negative test — non-translatable entity types must NOT incur translatable-listing behavior.

**Steps:**
1. Create `packages/listing/tests/Backend/NonTranslatableListingTest.php`:
   - Set up: register a non-translatable fixture entity type
   - Test cases:
     - `cacheContextsDoesNotIncludeLanguageContent` (negative assertion)
     - `cacheTagsDoNotIncludeLangcode` (negative assertion — only `entity:type` + `entity:type:id`)
     - `langcodeFilterRejectedAtValidation` — listing with `Filter::langcode('en')` on non-translatable type throws `UnsupportedListingException` at boot (WP10's validator)
     - `noImplicitLangcodeFilterAdded` — resolver does not auto-apply a langcode filter

**Files:** Test (~100 lines).

### T046 — `conformance-matrix.md` documentation

**Purpose:** Cross-backend behavior matrix as a planning artifact (lives in `kitty-specs/.../contracts/`, not in production docs).

**Steps:**
1. Create `kitty-specs/listing-pipeline-v1-01KRMN0B/contracts/conformance-matrix.md`:
   - Table: backend × scenario → expected behavior
   - Columns: sql-column, sql-blob, non-translatable
   - Rows: langcode filter (explicit), langcode filter (implicit), cache tags shape, cache contexts shape, sort on translatable field, sort on non-translatable field, validator rejection cases
   - For each cell: a 1-2 sentence expected behavior + test case ID
2. This document is reviewed in WP09's review cycle; final WP12 may move it under `docs/specs/` or `docs/conventions/` if it's load-bearing for downstream missions.

**Files:** Doc (~80 lines).

## Test strategy

Backend-specific tests use real SQLite + real fixture entity types. No mocking of storage. Reference M-006's `SqlBlobTranslatableTest` + `SqlColumnTranslatableTest` for the established pattern.

## Definition of Done

- [ ] All 3 backend tests exist and pass
- [ ] Conformance matrix doc exists with full cell coverage
- [ ] `vendor/bin/phpunit packages/listing/tests/Backend/` green
- [ ] `composer phpstan` + `composer cs-check` green
- [ ] Negative assertions are explicit (not just absence of assertion)

## Risks

| Risk | Mitigation |
|---|---|
| Fixture entity-type PSR-4 trap (M-006 lesson) | Place fixtures in own files under `packages/listing/tests/Fixtures/` — never inline in test class |
| sql-blob JSON1 not available in CI SQLite | Check SQLite version; if JSON1 missing, skip the test with a clear `markTestSkipped` |
| Translation table join semantics drift from M-006's shipped implementation | Tests read against the actual M-006-shipped `TranslationSchemaHandler` output; if it changes, tests catch the regression |
| Resolver implicit-langcode behavior depends on `RequestContext` state | Tests use a constructed `RequestContext` with a pinned active langcode; document the convention |

## Reviewer guidance

- Verify tests assert BOTH positive cases (sql-column joins correctly) AND negative cases (non-translatable doesn't get langcode treatment).
- Verify fixture entities are in own files (PSR-4) — repeat of M-006 #1457 fix.
- Verify the conformance matrix doc actually fills every cell (no "TBD" placeholders).
- Verify tests run in both Unit AND Integration suite invocations (CI runs them separately).

## Implementation command

```bash
spec-kitty agent action implement WP09 --agent <name>
```

## Activity Log

- 2026-05-16T20:49:52Z – claude:sonnet:python-implementer:implementer – shell_pid=37781 – Started implementation via action command
- 2026-05-16T20:57:49Z – claude:sonnet:python-implementer:implementer – shell_pid=37781 – WP09 ready: backend conformance matrix + tests. SqlColumn / SqlBlob / non-translatable scenarios covered. 16 tests passing. All gates green. conformance-matrix.md will be filed to planning branch separately.
