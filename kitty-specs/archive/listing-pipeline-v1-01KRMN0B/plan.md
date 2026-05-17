# Implementation Plan: Listing Pipeline v1

**Branch:** `main` (planning-and-merge target)
**Date:** 2026-05-15
**Spec:** [`spec.md`](spec.md)
**Doctrine spec:** [`docs/specs/listing-pipeline-v1.md`](../../docs/specs/listing-pipeline-v1.md)
**Mission ID:** M-007 (display) / `01KRMN0B4FWX9PK80RPSYDX1QM` (Spec Kitty)
**Slug:** `listing-pipeline-v1-01KRMN0B`

**Branch contract** (deterministic, from `spec-kitty agent mission setup-plan --json`):
- `current_branch`: `main`
- `planning_base_branch`: `main`
- `merge_target_branch`: `main`
- `branch_matches_target`: `true` ✓

## Summary

Ship the framework's Views-equivalent listing surface (per ADR 015) plus the cache tag/context architecture it requires. New L3 package `packages/listing/` carries `ListingDefinition`, `FilterDefinition`/`SortDefinition`/`Operator`, `HasListingsInterface` provider capability, `ListingResolver` with row-by-row access policy application + offset+limit pagination, exposed-filter URL parsing, definition validation at boot, and per-langcode listing support per M-006 C-002. New L0 cache additions: `TaggedCacheInterface` (tag-aware `setWithTags` + `invalidateByTag`), `ContextRegistry` + `ContextResolver` (canonical context names + deterministic resolution), event-driven invalidation via `ListingCacheInvalidator` subscribing to `AfterSaveEvent` / `AfterDeleteEvent` (event surface patched to carry `affectedLangcodes`). Charter §3.2 gains criterion 10 (beta-gate), new §5.X (listing) + §5.Y (cache) sections file at mission close. 12 WPs.

## Technical Context

| Field | Value |
|---|---|
| **Language / Version** | PHP 8.5+ (project minimum; `declare(strict_types=1)` mandatory) |
| **Primary Dependencies** | Symfony 7.x (EventDispatcher, Validator, Uid), Doctrine DBAL 4.x (for SQL backends executing EntityQuery), `waaseyaa/foundation` (LoggerInterface, RequestContext, EventDispatcher), `waaseyaa/entity` + `waaseyaa/entity-storage` (substrates from M-001 + M-006), `waaseyaa/cache` (existing key-value primitives being extended), `waaseyaa/access` (GateInterface for row-by-row policy application), `waaseyaa/typed-data` (FilterDefinition value coercion). |
| **Storage** | None new at the persistence layer (listings are read paths). Backend translation table (`<table>__translation`) shipped by M-006 is consumed for langcode filters; no new schema. `sql-blob` translation map probed via JSON path expressions. |
| **Testing** | PHPUnit 10.5 (no `-v`; rejected at this version). Contract tests with `#[CoversNothing]`, abstract `TestCase` subclassed per backend (`InMemoryListingResolverTest`, `SqliteListingResolverTest`) — same pattern as M-006's `TranslatableEntityContractTest`. Integration tests in `tests/Integration/Phase14/` (next phase number; verify against current tree at task-outline time). |
| **Target Platform** | PHP CLI + PHP-FPM under Caddy in production; PHP built-in server in dev. Linux x86_64 / WSL2. |
| **Project Type** | Monorepo PHP framework (62 packages, 7 layers). Touched layers: **L3** (new `listing` package), **L0** (`cache` additions, `foundation` AfterSaveEvent / AfterDeleteEvent patch). Layer discipline enforced by `bin/check-package-layers`. |
| **Performance Goals** | NFR-001 row-access overhead < 1 ms p95 / 50 ms per 50-row page (sentinel; not a CI gate). NFR-003 cache-hit overhead < 0.5 ms p95. NFR-004 zero new PHPStan / PHPUnit warnings (level 5). NFR-005 reference fixture resolves on both `InMemoryEntityStorage` and `DBALDatabase::createSqlite()`. |
| **Constraints** | C-001 layer graph: `listing` (L3) → `entity-storage` (L1) → `cache` (L0) → `foundation` (L0). No upward edges. C-002 no listing-specific EntityQuery predicates without a non-listing consumer. C-003 display stays in app land (ADR 013). C-004 EntityRepository / EntityQuery semantics unchanged. C-005 no `setTrustedProxies` access in cache package — RequestContext only. C-006 `ListingDefinition` instances safely shareable across requests; manifest-serializable. |
| **Scale / Scope** | 63 FRs, 5 NFRs, 6 Cs, 12 WPs. Spans `packages/listing/` (new), `packages/cache/` (additions), `packages/foundation/src/Event/AfterSaveEvent.php` (one-property patch), `packages/foundation/src/Event/AfterDeleteEvent.php` (mirror patch confirmed by §11.7 decision), `docs/specs/stability-charter.md` (§3.2 amendment + new §5.X + §5.Y), `docs/cookbook/listing-first-cut.md` (NEW), `docs/conventions/cache-tags-and-contexts.md` (NEW). |

## Charter Check

| Charter section | Gate | Status | Notes |
|---|---|---|---|
| **Testing Standards** | Contract + integration tests for new public surface. | PASS | Spec §8 lists 19 contract cases + 3 backend conformance subclasses + 3 integration tests. |
| **Quality Gates** | `composer phpstan` level 5, `composer cs-check`, `bin/check-package-layers`, `bin/check-composer-policy` green. | PASS | New `packages/listing/` declares L3 in composer manifest. Cache additions stay in L0. Foundation event patch is additive (new optional property). No upward edges; layer check clean. |
| **Performance Benchmarks** | NFR thresholds quantified. | PASS | NFR-001 / NFR-003 sentinels; NFR-004 / NFR-005 hard gates. |
| **Branch Strategy** | Plan/base/merge explicit and matched. | PASS | main → main → main. `branch_matches_target = true`. |
| **DIR-001 / DIR-002 / DIR-003** | Project directives. | PASS | No mission-specific override needed. |
| **Paradigm: domain-driven-design** | Entity / value-object / repository discipline. | PASS | `ListingDefinition` / `FilterDefinition` / `SortDefinition` / `ListingResult` / `Pagination` / `ExposedFilterValues` are pure value objects; `ListingResolver` is a domain service; `ListingDefinitionRegistry` is a repository-shape lookup. |
| **Charter §3.2 (beta entry)** | New criterion 10 ratified at mission close. | DEFERRED | Amendment lives in WP12. ADR 015 §Consequences pre-authored the wording. |
| **Charter §5.X / §5.Y (stable surface)** | New sections file at mission close. | DEFERRED | Amendment lives in WP12. Spec §6 enumerates the surface. |

**Re-evaluation post-Phase-1**: All gates re-checked after `data-model.md` and `contracts/` generation. PASS unchanged.

## Project Structure

### Mission documentation

```
kitty-specs/listing-pipeline-v1-01KRMN0B/
├── spec.md                          # 426 lines, committed at e1e455ebf
├── plan.md                          # this file
├── research.md                      # Phase 0 — §11 decision rationale + naming reconciliation
├── data-model.md                    # Phase 1 — value-object + service + event shapes
├── quickstart.md                    # Phase 1 — cookbook-style register-a-listing demo
├── contracts/                       # Phase 1 — stable-surface contracts
│   ├── listing-definition.md         # ListingDefinition + FilterDefinition + SortDefinition + Operator
│   ├── listing-resolver.md           # ListingResolver + ListingResult + Pagination
│   ├── exposed-filters.md            # ExposedFilterParser + ExposedFilterValues + coercer
│   ├── tagged-cache.md               # TaggedCacheInterface + tag-string vocabulary
│   ├── context-architecture.md       # ContextRegistry + ContextResolver + canonical names
│   └── lifecycle-event-patch.md      # AfterSaveEvent / AfterDeleteEvent affectedLangcodes
├── checklists/requirements.md       # specify-phase validation
├── meta.json
└── status.events.jsonl
```

### Source paths touched

```
packages/listing/                                  # NEW package, L3
├── composer.json                                  # WP01 — manifest, autoload, layer declaration
├── src/
│   ├── ListingDefinition.php                      # WP01 — value object
│   ├── FilterDefinition.php                       # WP01 — value object
│   ├── SortDefinition.php                         # WP01 — value object
│   ├── Operator.php                               # WP01 — backed enum
│   ├── SortDirection.php                          # WP01 — backed enum
│   ├── Filter.php                                 # WP01 — static factory sugar
│   ├── Sort.php                                   # WP01 — static factory sugar
│   ├── Pagination.php                             # WP01 — value object
│   ├── ListingResult.php                          # WP01 — value object
│   ├── ListingResolver.php                        # WP05/WP06/WP07 — orchestrator service
│   ├── ListingDefinitionRegistry.php              # WP02 — id → definition lookup
│   ├── ListingDefinitionValidator.php             # WP11 — boot-time validation
│   ├── ListingCacheKeyBuilder.php                 # WP07 — deterministic key emission
│   ├── ListingCacheInvalidator.php                # WP08 — AfterSave/AfterDelete subscriber
│   ├── ExposedFilterParser.php                    # WP09 — URL → ExposedFilterValues
│   ├── ExposedFilterValues.php                    # WP09 — value object
│   ├── ExposedFilterCoercer.php                   # WP09 — typed-data coercion
│   ├── HasListingsInterface.php                   # WP02 — provider capability
│   ├── ServiceProvider.php                        # WP12 — DI wiring
│   └── Exception/
│       ├── UnsupportedListingException.php        # WP01 — definition-time error
│       ├── UnknownListingException.php            # WP02 — registry miss
│       └── ListingCoercionException.php           # WP09 — exposed-filter coercion internal-only
└── tests/
    ├── Contract/
    │   ├── ListingResolverContract.php            # WP05-WP10 — abstract test (CoversNothing)
    │   ├── InMemoryListingResolverTest.php        # WP05 — InMemoryEntityStorage subclass
    │   └── SqliteListingResolverTest.php          # WP05 — DBALDatabase::createSqlite() subclass
    ├── Backend/
    │   ├── SqlColumnTranslatableListingTest.php   # WP10 — langcode join
    │   ├── SqlBlobTranslatableListingTest.php     # WP10 — JSON-map probe
    │   └── NonTranslatableListingTest.php         # WP10 — no language.content context
    └── Fixtures/
        └── UpcomingEventsListing.php              # WP12 — reference consumer fixture

packages/cache/                                    # ADDITIONS at L0
├── src/
│   ├── TaggedCacheInterface.php                   # WP03 — NEW interface
│   ├── MemoryBackend.php                          # WP03 — extend with tag indexing
│   ├── ContextRegistry.php                        # WP04 — NEW
│   ├── ContextResolver.php                        # WP04 — NEW
│   ├── ContextNames.php                           # WP04 — canonical string constants
│   └── Exception/
│       └── InvalidCacheTagException.php           # WP03 — NEW
└── tests/
    ├── Unit/
    │   ├── TaggedCacheInterfaceContractTest.php   # WP03 — abstract (CoversNothing)
    │   ├── MemoryBackendTaggedTest.php            # WP03 — concrete
    │   └── ContextResolverTest.php                # WP04 — concrete

packages/foundation/                               # MINIMAL surface patch
└── src/Event/
    ├── AfterSaveEvent.php                         # WP08 — add ?array $affectedLangcodes = null
    └── AfterDeleteEvent.php                       # WP08 — mirror addition (§11.7 decision)

packages/entity-storage/                           # MINIMAL: backfill affectedLangcodes
└── src/
    └── SqlStorageDriver.php                       # WP08 — populate $affectedLangcodes on translatable saves

tests/Integration/Phase14/                         # NEW (verify next-phase number at task-outline)
├── ListingPipelineIntegrationTest.php             # WP05+WP09 — full HTTP flow
├── ListingCacheInvalidationIntegrationTest.php    # WP08 — save → re-resolve cache miss
└── BootValidationFailureTest.php                  # WP11 — kernel refuses to boot on bad definition

docs/
├── specs/
│   ├── listing-pipeline-v1.md                     # post-mortem stamp at mission close (WP12)
│   ├── public-surface-map.md                      # WP12 — register listing + cache new surface
│   └── stability-charter.md                       # WP12 — §3.2.10 criterion + §5.X + §5.Y
├── cookbook/
│   └── listing-first-cut.md                       # WP12 — NEW
└── conventions/
    └── cache-tags-and-contexts.md                 # WP12 — NEW

CLAUDE.md                                          # WP12 — orchestration row for packages/listing/* + cache row update
CHANGELOG.md                                       # WP12 — [Unreleased] Added bullet
```

## Phase 0: Research

See [`research.md`](research.md). Open questions resolved:

1. **§11.1 page-size cap** — Cap at 1000 + `ListingDefinition::allowUnbounded()` opt-out. Boot-time validator (FR-051) rejects oversized or null `pageSize` unless the listing explicitly opted out. Rationale: foot-gun prevention with explicit override for fixture / admin / CLI bulk listings.
2. **§11.2 validation timing** — Boot-time via `PackageManifestCompiler::warm()` post entity-type registration, pre-route dispatch. Dev runs on every request; prod runs only on manifest rebuild. Rationale: fail-fast in dev; zero per-request overhead in prod.
3. **§11.3 approximateTotal shape** — Per-listing flag on `ListingDefinition`. Validator rejects `approximateTotal: true` combined with `pageSize === null + allowUnbounded()` (the combination has no useful semantics: total count is null on the same listing that returns every row anyway).
4. **§11.4 cache TTL** — Infinite default; `?int $cacheTtl = null` constructor param on `ListingDefinition` passes through to `TaggedCacheInterface::setWithTags`. Rationale: event-driven invalidation makes TTL a cost-control knob, not a correctness mechanism.
5. **§11.5 event subscription priority** — `ListingCacheInvalidator` subscribes to `AfterSaveEvent` and `AfterDeleteEvent` at **priority=100** (high — runs first in chain). Rationale: subsequent listeners that re-resolve listings see clean (invalidated) cache.
6. **§11.6 strict-mode parser** — `ExposedFilterParser::strict()` fluent factory returns a strict variant. Production parser uses silent-drop per FR-044; test environments call `strict()` to throw `ListingCoercionException` instead. Same instance class; different internal flag.
7. **§11.7 AfterSaveEvent / AfterDeleteEvent patch** — Both events gain optional `readonly ?array $affectedLangcodes = null` property. `SqlStorageDriver`'s translatable write path backfills the array; non-translatable saves leave it `null`. `ListingCacheInvalidator` falls back to `[$entity->activeLangcode()]` when `null`. Additive surface change in `packages/foundation`.

Plus naming / pattern reconciliation:

8. **Operator enum naming** — `Waaseyaa\Listing\Operator::EQ` etc. Matches PHP convention (`enum Operator` with backed string values `'eq'`, `'neq'`, …). No conflict with existing `Waaseyaa\Validation\*` operators (validation-layer rules are distinct).
9. **`Filter` / `Sort` factory class location** — Static factories live in `Waaseyaa\Listing\Filter` and `Waaseyaa\Listing\Sort` sibling classes (not on `FilterDefinition` directly) per ADR 015 example code (`Filter::gte('starts_at', 'now')`).
10. **`HasListingsInterface` discovery** — `PackageManifestCompiler::warm()` discovers via `instanceof HasListingsInterface` check on each registered `ServiceProvider`. Mirrors `HasNativeCommandsInterface` discovery shape. Results cached to `var/manifest.php`. No new attribute discovery path.
11. **Tag-string regex enforcement** — `[a-z][a-z0-9_:.-]*` enforced in `TaggedCacheInterface::setWithTags()`, throws `InvalidCacheTagException`. No silent normalisation (lesson from M-002 / M-006 codified-context discipline).
12. **No backwards-compat for cache.setWithTags** — `TaggedCacheInterface` is NEW; the existing `CacheInterface` is unchanged. Apps using only `CacheInterface` see no change; opting into tag-aware operations means depending on the new interface explicitly.

## Phase 1: Design

See:
- [`data-model.md`](data-model.md) — value object + service + event domain shapes
- [`contracts/`](contracts/) — stable-surface contracts (6 files)
- [`quickstart.md`](quickstart.md) — cookbook-style register-a-listing demo

Re-evaluating Charter Check after Phase 1 design: PASS unchanged.

## Complexity tracking

| Item | Why it could be complex | Mitigation |
|---|---|---|
| Per-row access policy under high cardinality | `Pagination::$totalRows` requires scanning the full result set through `GateInterface::allows` per row; on a 10,000-row listing that's 10,000 gate calls. | NFR-002 `approximateTotal` opt-out skips the full scan and returns `null` for `$totalRows`. Per-listing flag is declarative — the listing author chooses. FR-032 fast-path opt-in on the policy class skips the per-row loop when the policy returns unconditional `Neutral`/`Allowed`. |
| Cache tag invalidation cardinality on bulk writes | A batch update touching 1000 entities of one type fires 1000 `AfterSaveEvent`s → 1000+ tag invalidations. Cache backend's `invalidateByTag` cost dominates. | `MemoryBackend` indexes tags as `tag → set<key>`; invalidation is O(1) per tag + O(matched keys). For large invalidation sets, the listing cache absorbs the cost (each invalidation re-fills on next resolve). v1.x perf mission may add batch-invalidate API. |
| Definition validation under hot reload (dev mode) | Validating every listing definition on every request adds boot cost in dev. | Validator is fast in practice: ~1ms per listing on a Sonnet-class machine for a typical 4-filter listing. Acceptable dev startup cost; prod runs from cached `var/manifest.php`. |
| ContextResolver determinism across processes | Different PHP workers must resolve the same context values to the same strings for cache-key parity. | `resolve()` is pure: reads `RequestContext` (deterministic — set once per request); returns canonical sorted strings (e.g. roles joined sorted-ascending). Contract test asserts determinism with fuzzed inputs. |
| Exposed-filter type coercion under adversarial input | User-supplied `$_GET` values feed the coercer; bad input must never throw in production. | FR-044 silent-drop + debug-log; strict mode opt-in for test envs only. Coercer is built atop `waaseyaa/typed-data` which already has the type lattice (no new coercion logic invented here). |
| Langcode handling for non-translatable entity types | A listing of a non-translatable entity type must NOT add `language.content` to its cache contexts. | Definition validator (FR-051(f)) rejects `langcode` filters on non-translatable types; resolver branches on `$entityType->isTranslatable()` for the implicit context contribution. Backend conformance tests cover both paths. |

## Progress tracking

| Phase | Status | Date |
|---|---|---|
| Specify | ✅ DONE | 2026-05-15 (commit `e1e455ebf`) |
| Plan (this file) | 🔄 IN PROGRESS | 2026-05-15 |
| Tasks outline | ⏳ pending | — |
| Tasks packages | ⏳ pending | — |
| Tasks finalize | ⏳ pending | — |
| Implement-review loop | ⏳ pending | — |
| Merge | ⏳ pending | — |

## ⛔ Mandatory Stop

This command (`/spec-kitty.plan`) is COMPLETE after generating the planning artifacts above. The next commands are `/spec-kitty.tasks-outline` → `/spec-kitty.tasks-packages` → `/spec-kitty.tasks-finalize` → implement-review loop dispatch.
