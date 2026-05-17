# Listing Pipeline — Backend Conformance Matrix

Mission: `listing-pipeline-v1-01KRMN0B`
Work package: WP09 — Backend conformance tests (SqlColumn / SqlBlob / non-translatable)
Authoritative source: `docs/specs/listing-pipeline-v1.md` §8.2

This matrix documents which backend conformance test covers which FR/NFR in
which storage topology. It is the planning artifact that the per-WP reviewer
and the M-007 mission reviewer use to verify cross-backend coverage of the
listing pipeline.

The three test classes are all under `packages/listing/tests/Backend/`:

| Class | Topology | Storage |
|---|---|---|
| `SqlColumnTranslatableListingTest` | Primary table + `<table>__translation` sidecar | M-006 sql-column |
| `SqlBlobTranslatableListingTest` | Primary table with widened `(id, langcode)` PK + `_data` JSON blob | M-006 sql-blob |
| `NonTranslatableListingTest` | Single primary table, no langcode column | Legacy non-translatable |

All three tests use `DBALDatabase::createSqlite()` (`:memory:`), wire a
`SqlStorageDriver` through `SingleConnectionResolver`, and resolve through
the same `ListingResolver` constructor signature the `ListingResolverContract`
contract suite uses. Schemas are hand-rolled (rather than going through
`EntitySchemaSync`) to keep the test focused on the resolver contract.

## Coverage matrix

| Requirement | sql-column | sql-blob | non-translatable |
|---|---|---|---|
| **FR-023** — `cacheTags` include `entity:<type>`, `entity:<type>:<id>`, and (translatable) `entity:<type>:<id>:<langcode>` | `cacheTagsIncludeLangcodePerRow` — asserts `entity:article:42:en` | `cacheTagsIncludeLangcodePerRow` — asserts `entity:article:42:mi-tle` | `cacheTagsDoNotIncludeLangcodeSuffix` — regex-asserts no `:lang` suffix |
| **FR-046** — explicit `Filter::langcode($code)` narrows results | `listingFiltersByLangcodeExplicit` — primary-table `default_langcode = en` | `listingFiltersByLangcodeExplicit` — primary-table `langcode = en` | n/a (non-translatable types have no langcode field) |
| **FR-047** — implicit langcode filter from `RequestContext.activeLangcode` for translatable types | `listingFiltersByLangcodeImplicit` — `activeLangcode='fr'` narrows | `listingFiltersByLangcodeImplicit` — `activeLangcode='fr'` narrows across composite-PK rows | n/a (translatable-only behaviour; verified inverse via `resolverDoesNotJoinTranslationTable`) |
| **FR-048** — `cacheContexts` includes `language.content` for translatable types | `cacheContextsIncludeLanguageContent` | `cacheContextsIncludeLanguageContent` | `cacheContextsDoNotIncludeLanguageContent` — asserts inverse |
| **NFR-005** — backend agnosticism (resolver behaviour identical across backends) | `untranslatedFieldUsesPrimaryTable` + `sortFieldOnTranslationTableJoinedCorrectly` | `untranslatedFieldUsesPrimaryTable` + `sortFieldOnTranslationTableJoinedCorrectly` (skipped without JSON1) | `filtersAndSortsApplyToPrimaryTable` + `resolverDoesNotJoinTranslationTable` |

## Per-test FR mapping (FR-id → test name)

### `SqlColumnTranslatableListingTest`
- FR-046 → `listingFiltersByLangcodeExplicit`
- FR-047 → `listingFiltersByLangcodeImplicit`
- FR-023 → `cacheTagsIncludeLangcodePerRow`
- FR-048 → `cacheContextsIncludeLanguageContent`
- FR-019 / NFR-005 → `untranslatedFieldUsesPrimaryTable`
- FR-014 / FR-019 → `sortFieldOnTranslationTableJoinedCorrectly`

### `SqlBlobTranslatableListingTest`
- FR-046 → `listingFiltersByLangcodeExplicit`
- FR-047 → `listingFiltersByLangcodeImplicit`
- FR-023 → `cacheTagsIncludeLangcodePerRow`
- FR-048 → `cacheContextsIncludeLanguageContent`
- FR-019 / NFR-005 → `untranslatedFieldUsesPrimaryTable`
- FR-014 / FR-019 → `sortFieldOnTranslationTableJoinedCorrectly` (skips when SQLite JSON1 absent)

### `NonTranslatableListingTest`
- FR-048 inverse → `cacheContextsDoNotIncludeLanguageContent`
- FR-023 (translatable-case inverse) → `cacheTagsDoNotIncludeLangcodeSuffix`
- NFR-005 → `resolverDoesNotJoinTranslationTable`
- FR-019 → `filtersAndSortsApplyToPrimaryTable`

## Environmental dependencies

| Test | Dependency | Behaviour when unavailable |
|---|---|---|
| `SqlBlobTranslatableListingTest::sortFieldOnTranslationTableJoinedCorrectly` | SQLite JSON1 extension (`json_extract`) | `markTestSkipped` with explanatory message |

The current PHP/SQLite build in this monorepo includes JSON1; the skip is a
forward-safety guard for any environment that lacks it (R-blob-json1 in the
WP09 prompt).

## Why this lives in `kitty-specs/` and not `docs/specs/`

This matrix is a planning artifact: it documents which test covers which case
for the M-007 mission reviewer. The enduring architectural contract
(translatable behaviour for the listing pipeline) lives in
`docs/specs/listing-pipeline-v1.md` §8.2 — that file is the source of truth
for what the matrix maps onto. When the listing pipeline ships and M-007 is
merged, this matrix can be referenced by the mission post-mortem but does
not need ongoing maintenance.
