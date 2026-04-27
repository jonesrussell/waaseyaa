# Implementation Plan: Single-Entity Work Surface

**Branch**: `main` | **Date**: 2026-04-27 | **Spec**: [spec.md](spec.md)
**Mission**: `single-entity-work-surface-01KQ7M1P`

## Summary

Ship a reusable Layer 1–3 capability bundle that exposes six primitives for editing one entity at a time:

1. **F1** — entity deep-link route helper (extends `waaseyaa/routing`, L4)
2. **F2** — bundle-keyed field-template registry, attribute-driven, **enriching** the existing `FieldDefinition` (extends `waaseyaa/field`, L1)
3. **F3** — per-field auto-save endpoint (extends `waaseyaa/api`, L4)
4. **F4** — attachment with active-reference relationship (new `waaseyaa/attachment`, L2)
5. **F5** — pluggable structured-import pipeline with default in-house GFM table parser (new `waaseyaa/structured-import`, L3)
6. **F6** — schema-driven form-descriptor builder, structured-array output only, no HTML (extends `waaseyaa/field`, L1)

Architectural locks confirmed during planning interrogation:

- **Q1: A** — F2 enriches `Waaseyaa\Field\FieldDefinition` with optional `group` and `promptAliases` properties; one canonical registry (`FieldDefinitionRegistry`), no parallel `BundleTemplateRegistry`. Honors CLAUDE.md "dual-state bug pattern" rule.
- **Q2: A** — F6 emits `FormFieldDescriptor` value objects only. No HTML, no Twig, no Vue rendering inside `field`. Layer hygiene preserved.
- **DIR-003** (Greenfield Removal Policy, mission #5, merged) — breaking changes to `FieldDefinition`, `RouteBuilder`, `JsonApiController`, or any other touchpoint are acceptable and preferred when they remove duplication. No `@deprecated` wrappers, no `Legacy*` namespaces.

## Technical Context

**Language/Version**: PHP 8.4+ (`declare(strict_types=1)` in every file). Use PHP 8.4 features where they improve clarity (named constructor parameters, asymmetric visibility, readonly properties, property hooks where they fit). No fallbacks for older PHP versions.

**Primary Dependencies**:
- Existing first-party: `waaseyaa/foundation`, `waaseyaa/entity`, `waaseyaa/entity-storage`, `waaseyaa/field`, `waaseyaa/access`, `waaseyaa/routing`, `waaseyaa/api`.
- Third-party: Symfony 7.x components already in monorepo (Routing, EventDispatcher, Validator, Uid). **No new runtime Composer dependencies** introduced by this mission (NFR-008 enforced).

**Storage**: SQLite for development/testing (`DBALDatabase::createSqlite()`); MySQL/PostgreSQL via Doctrine DBAL in production. Attachment entity uses standard `EntityRepository` + `SqlStorageDriver` pipeline per `.claude/rules/entity-storage-invariant.md`.

**Testing**:
- Unit tests in `packages/<pkg>/tests/Unit/` (PHPUnit 10.5, no `-v` flag, `#[Test]` / `#[CoversClass]` attributes).
- Integration tests in `tests/Integration/PhaseN/` covering the cross-primitive flow (Success Criterion 5).
- Contract tests in `packages/structured-import/tests/Contract/` for `StructuredImporterInterface`.
- Concurrency test for F4 `setActive` (NFR-010): fixture-driven, 50 concurrent calls, exactly one active.
- In-memory fixtures: `InMemoryEntityStorage`, `MemoryStorage`, `DBALDatabase::createSqlite()`.

**Target Platform**: PHP 8.4 CLI + PHP built-in dev server. Production targets Linux (any). Charter mandates CI matrix: PHP 8.4 × Ubuntu/Debian × SQLite/Postgres.

**Project Type**: Backend framework primitives. No frontend code in this mission (admin SPA integration is explicitly out of scope per spec).

**Performance Goals** (from spec NFRs):
- Auto-save endpoint: p95 ≤ 50 ms (NFR-001).
- Field-template registry boot: ≤ 5 ms for 100 bundles × 10 fields (NFR-003).
- GFM parser: 1 MiB document with ≤ 2× memory footprint (NFR-004).

**Constraints**:
- Auto-save body cap: 64 KiB default, configurable per route option (NFR-002).
- Layer compliance via `bin/check-package-layers` with zero exemptions (NFR-005).
- Composer policy via `bin/check-composer-policy` (NFR-006).

**Scale/Scope**: 6 primitives, 3 existing packages extended, 2 new packages. Estimated ~30 new PHP files + tests.

## Charter Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Directive | Status | Notes |
|---|---|---|
| **DIR-001** (Risk Boundaries) | ✅ Pass | No Drupal globals, no service locators, no raw PDO (entity-storage invariant), no skipped hooks, no secrets. All wiring through `ServiceProvider`. Public-API breaking changes (e.g., adding `group`/`promptAliases` constructor params to `FieldDefinition`) are explicit and announced via CHANGELOG.md + UPGRADING.md per DIR-001's communication discipline. |
| **DIR-002** (Documentation Synchronization) | ✅ Pass | Plan updates `docs/specs/entity-system.md` (field definition surface), `docs/specs/api-layer.md` (auto-save endpoint), `docs/specs/access-control.md` (attachment policy delegation), and adds `docs/specs/work-surface.md` (new subsystem). |
| **DIR-003** (Greenfield Removal Policy) | ✅ Pass | Mission embraces breaking changes — `FieldDefinition` constructor signature change, possible `RouteBuilder` extension method, possible `JsonApiController` route registration change. No `@deprecated` wrappers; no `Legacy*` shims. Spec C-010 already aligned. |
| Layer architecture | ✅ Pass | F2/F6 at L1 (field), F4 at L2 (new), F5 at L3 (new), F1/F3 at L4 (routing/api). All downward dependencies. `bin/check-package-layers` will pass. |
| Quality Gates (charter § Quality Gates) | ✅ Pass plan-time | PHPUnit, PHPStan, composer policy, package layers, and CHANGELOG entries all required at merge. NFR-005, NFR-006, NFR-007 codify them. |
| Performance Targets | ✅ Pass plan-time | NFR-001/003/004 set explicit budgets. Benchmark harness at `tools/bench/` to assert auto-save p95. Will add at least one benchmark if budgets become NFR-asserting tests. |
| Testing Standards | ✅ Pass plan-time | NFR-007 mandates unit + integration + contract + concurrency tests. SQLite + Postgres matrix is enforced by CI; no opt-out. |

**Result**: Charter check passes. No charter exceptions filed; no complexity tracking violations.

## Project Structure

### Documentation (this feature)

```
kitty-specs/single-entity-work-surface-01KQ7M1P/
├── spec.md
├── meta.json
├── plan.md                # this file
├── research.md            # Phase 0 — open questions resolved against existing code
├── data-model.md          # Phase 1 — entities, value objects, registry shapes
├── quickstart.md          # Phase 1 — end-to-end wire-up walkthrough
├── contracts/
│   ├── F1-deep-link-route-helper.md
│   ├── F2-field-template-attributes.md
│   ├── F3-auto-save-endpoint.md
│   ├── F4-attachment-repository.md
│   ├── F5-structured-importer.md
│   └── F6-form-descriptor-builder.md
├── checklists/
│   └── requirements.md
└── tasks/                 # populated by /spec-kitty.tasks
```

### Source Code (repository root)

Five packages touched. Two new, three extended.

```
packages/field/                                        # extend (L1)
├── src/
│   ├── Attribute/
│   │   ├── BundleTemplate.php                          # NEW — class-level attribute
│   │   └── FieldTemplate.php                           # NEW — repeatable attribute on bundle classes
│   ├── FieldDefinition.php                             # EDIT — add $group, $promptAliases (constructor + getters)
│   ├── FieldDefinitionInterface.php                    # EDIT — add getGroup(), getPromptAliases() to interface
│   ├── Form/
│   │   ├── FormFieldDescriptor.php                     # NEW — readonly value object
│   │   └── FormDescriptorBuilder.php                   # NEW — F6 builder (no HTML)
│   ├── BundleTemplateCompiler.php                      # NEW — attribute → registerBundleFields()
│   └── ServiceProvider.php                             # EDIT — register compiler + builder
├── tests/
│   ├── Unit/
│   │   ├── FieldDefinitionTest.php                     # EDIT — cover new params
│   │   ├── BundleTemplateCompilerTest.php              # NEW
│   │   └── Form/FormDescriptorBuilderTest.php          # NEW
│   └── ...

packages/routing/                                      # extend (L4)
├── src/
│   ├── EntityDeepLinkRouteBuilder.php                  # NEW — F1 helper (wraps RouteBuilder)
│   └── ParamConverter/
│       └── EntityResolverParamConverter.php            # NEW or EDIT — repository-backed resolver
├── tests/Unit/EntityDeepLinkRouteBuilderTest.php       # NEW

packages/api/                                          # extend (L4)
├── src/
│   ├── Controller/
│   │   └── FieldAutoSaveController.php                 # NEW — F3 single endpoint
│   ├── JsonApiRouteProvider.php                        # EDIT — register PUT /<api>/{type}/{id}/field/{key}
│   └── Exception/
│       └── PayloadTooLargeException.php                # NEW — for 422 with size cap
├── tests/Integration/Phase##/FieldAutoSaveTest.php     # NEW

packages/attachment/                                   # NEW (L2)
├── composer.json
├── src/
│   ├── Attachment.php                                  # ContentEntityBase subclass
│   ├── AttachmentRepository.php                        # listFor / setActive / getActive
│   ├── Policy/
│   │   └── ParentDelegatedAccessPolicy.php             # #[PolicyAttribute(entityType: 'attachment')]
│   ├── Schema/
│   │   └── AttachmentSchema.php                        # SqlSchemaHandler config
│   └── ServiceProvider.php
├── tests/
│   ├── Unit/AttachmentRepositoryTest.php
│   ├── Integration/SetActiveConcurrencyTest.php        # NFR-010
│   └── Policy/ParentDelegatedAccessPolicyTest.php

packages/structured-import/                            # NEW (L3)
├── composer.json
├── src/
│   ├── StructuredImporterInterface.php
│   ├── ImportResult.php                                # readonly value object
│   ├── Gfm/
│   │   ├── GfmTableImporter.php                        # default implementation
│   │   ├── GfmTableParser.php                          # in-house parser
│   │   └── PromptNormalizer.php                        # case-fold + collapse whitespace + trim
│   └── ServiceProvider.php
├── tests/
│   ├── Contract/StructuredImporterContractTest.php
│   ├── Unit/Gfm/GfmTableParserTest.php
│   └── Integration/Phase##/EndToEndImportTest.php

tests/Integration/Phase##/SingleEntityWorkSurfaceTest.php  # NEW — Success Criterion 5
docs/specs/work-surface.md                                  # NEW — subsystem doc
docs/specs/entity-system.md                                 # EDIT — note FieldDefinition extensions
docs/specs/api-layer.md                                     # EDIT — auto-save endpoint contract
docs/specs/access-control.md                                # EDIT — parent-delegated policy pattern

CHANGELOG.md                                                # EDIT — Added/Changed entries (DIR-001 communication discipline)
UPGRADING.md                                                # EDIT — FieldDefinition constructor change recipe
```

**Structure Decision**: Multi-package backend addition. Adheres to Waaseyaa monorepo convention — each new package is a sibling under `packages/`, with its own `composer.json` carrying path-based references to lower-layer packages. Root `composer.json` adds `@dev` constraints for the two new packages.

## Phase 0 — Research (see research.md)

Open questions to resolve before Phase 1 design. All grounded in existing code; no speculation.

1. **`FieldDefinition` is `final readonly`** — adding properties means adding constructor parameters. What is the cleanest signature evolution that doesn't bloat the constructor and stays tractable? (Resolved: append `string $group = '', array $promptAliases = []` at the end. PHP 8.4 named-args make existing call sites still readable.)
2. **`PolicyAttribute` placement** — `Waaseyaa\Access\Gate\PolicyAttribute` exists. The new `Attachment` entity gets a delegating policy registered with `#[PolicyAttribute(entityType: 'attachment')]`. Verify the discovery path (`AbstractKernel::discoverAccessPolicies()`) finds attribute-decorated classes in new packages without changes.
3. **`RouteBuilder` extensibility** — `RouteBuilder` is `final` with `private __construct`. F1's helper either:
   - Composes `RouteBuilder` (consumer's `EntityDeepLinkRouteBuilder` calls `RouteBuilder::create(...)` internally and returns the configured `Route`), OR
   - Adds a static factory method to `RouteBuilder` itself (touches `routing` directly).

   Choose between composition vs direct addition; both are non-breaking under DIR-003 latitude.
4. **`JsonApiRouteProvider` pattern** — does the auto-save endpoint go through `JsonApiRouteProvider`'s automatic resource registration, or does it register a one-off route for the single endpoint? Inspect the provider's current shape to decide.
5. **Attachment schema** — Waaseyaa entity types use `SqlSchemaHandler` with `_data` JSON blob for non-schema columns. Decide which fields are first-class columns (`id`, `uuid`, `parent_entity_type`, `parent_entity_id`, `is_active`, `filename`, timestamps) vs `_data` (storage_uri, content_type, size).
6. **`setActive` atomicity** — Waaseyaa has `UnitOfWork` (per `EntityRepository::saveMany()`); confirm it gives the right transactional boundary for "clear active on siblings + set active on target" or whether we need a direct `DatabaseInterface` transaction wrapper around two `EntityRepository::save()` calls.
7. **GFM table parser scope** — what's the minimum subset of GFM table syntax to support? (Decision target: pipe-delimited rows, optional leading/trailing pipe, header separator with `:---:` alignment markers ignored, escaped pipes via `\|`. No nested tables. No HTML.)
8. **Prompt normalization edge cases** — should normalization preserve diacritics? (Decision target: yes — Unicode-safe `mb_strtolower`, no transliteration. C-012 forbids fuzzy matching, so the bar is "case + whitespace differences only".)

## Phase 1 — Design (see data-model.md, contracts/, quickstart.md)

### Data model summary (full detail in data-model.md)

| Concept | Kind | Package | Notes |
|---|---|---|---|
| `Attachment` | Content entity | `attachment` | Parent reference (entity_type + id), `is_active` flag, file metadata. |
| `FieldDefinition` (enriched) | Value object | `field` | Adds `group: string`, `promptAliases: list<string>`. Stays `final readonly`. |
| `BundleTemplate` attribute | Class attribute | `field` | Class-level. Declares entity_type + bundle. |
| `FieldTemplate` attribute | Repeatable property/method attribute | `field` | Declares one field's key/label/group/aliases. |
| `FormFieldDescriptor` | Readonly value object | `field` | name, type, label, group, value, readOnly, required, errors. |
| `ImportResult` | Readonly value object | `structured-import` | matched, unmatched, errors. |
| `StructuredImporterInterface` | Interface | `structured-import` | One method: `import(string $payload, string $entityTypeId, ?string $bundle): ImportResult`. |

### Contract summaries (full detail in contracts/)

- **F1**: `EntityDeepLinkRouteBuilder::create(string $segment, string $entityTypeId): RouteBuilder`. Caller chains controller/access/methods on the returned builder. Resolution wired via param converter that uses `EntityRepositoryInterface` and applies `AccessPolicyInterface` via `EntityAccessHandler`.
- **F2**: `#[BundleTemplate(entityType: 'node', bundle: 'article')]` on a class; repeatable `#[FieldTemplate(key: 'title', label: 'Title', type: 'string', promptAliases: ['title'])]` on properties or methods. Compiler scans via `PackageManifestCompiler` and calls `FieldDefinitionRegistry::registerBundleFields()`.
- **F3**: `PUT /<api>/{entityType}/{id}/field/{key}` with `{"value": "<string>"}`, idempotent, body cap configurable, returns 200/401/403/404/415/422.
- **F4**: `Attachment` entity + `AttachmentRepository::listFor() / setActive() / getActive() / save() / delete()`. `ParentDelegatedAccessPolicy` looks up parent via `EntityRepository`, delegates view/update.
- **F5**: `GfmTableImporter` parses 2-column tables, normalizes prompts (`mb_strtolower` + `preg_replace('/\s+/u', ' ')` + `trim`), matches against `FieldDefinition::getPromptAliases()` from F2's enriched registry.
- **F6**: `FormDescriptorBuilder::build(EntityInterface $entity, string $bundle, ?AccountInterface $account = null): list<FormFieldDescriptor>`. Walks `FieldDefinitionRegistry::bundleFieldsFor()`. Honors `FieldAccessPolicyInterface` to mark read-only.

### Quickstart (full detail in quickstart.md)

End-to-end consumer wire-up showing: declare a `BundleTemplate` class with `FieldTemplate`s; register an `EntityDeepLinkRouteBuilder` route in a `ServiceProvider`; auto-save fires through the API endpoint; an attachment is uploaded; an import populates the form. This is the integration test target for Success Criterion 5.

## Phase 2 / 3 (NOT covered by this command)

- **Phase 2**: `/spec-kitty.tasks` will break this plan into work packages — likely 6–10 WPs given the surface area:
  - WP01: Foundation — extend `FieldDefinition` + interface + tests + UPGRADING entry.
  - WP02: F2 — `BundleTemplate`/`FieldTemplate` attributes + `BundleTemplateCompiler` + tests.
  - WP03: F6 — `FormFieldDescriptor` + `FormDescriptorBuilder` + tests.
  - WP04: F4a — `Attachment` entity + storage schema + `AttachmentRepository` (no policy yet).
  - WP05: F4b — `ParentDelegatedAccessPolicy` + concurrency test for `setActive`.
  - WP06: F1 — `EntityDeepLinkRouteBuilder` + param converter + tests.
  - WP07: F3 — `FieldAutoSaveController` + route registration + integration test.
  - WP08: F5a — `StructuredImporterInterface` + `ImportResult` + `GfmTableParser` + parser unit tests.
  - WP09: F5b — `GfmTableImporter` + prompt matching + contract test.
  - WP10: Cross-cutting — end-to-end integration test (Success Criterion 5), `docs/specs/work-surface.md`, CHANGELOG/UPGRADING entries.

  The tasks command may collapse some of these or split further based on owned-files conflicts.

- **Phase 3**: `/spec-kitty.implement` (or this skill's implement-review loop) executes the WPs.

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| `FieldDefinition` constructor change cascades to many call sites | High | Default values for new params keep old call sites compiling. UPGRADING.md explains the named-arg upgrade path. DIR-003 explicitly permits the breaking change. |
| `EntityDeepLinkRouteBuilder` blurs L4 boundaries (entity resolution from a routing helper) | Medium | The helper accepts callbacks, not entity instances. Resolution happens inside a param converter that calls `EntityRepositoryInterface` (lower layer — allowed). Verify in research.md Q3. |
| Attribute scanning at boot adds ≥ 5 ms (NFR-003 violation) | Medium | Use existing `PackageManifestCompiler` cache; only scan once on cache miss. Benchmark with 100 bundles before merge. |
| `setActive` race condition (NFR-010) | Medium | `UnitOfWork` transactional boundary around UPDATE-clear-siblings + UPDATE-set-target. Concurrency test asserts invariant under 50 concurrent calls. If `UnitOfWork` is too coarse, drop to direct DBAL transaction (still through `DatabaseInterface`). |
| GFM parser corner cases (escaped pipes, alignment markers, multi-table docs) | Medium | Spec out the supported subset in research.md Q7. Reject unsupported syntax explicitly via `errors`, never silently. |
| Prompt-alias collision across bundles (same alias, different fields) | Low | Compiler validates uniqueness per `(entity_type, bundle)`; throws at boot, not at import time. |
| F2 attribute scanning miscompiles when consumer hasn't run `composer dump-autoload --optimize` | Medium | `PackageManifestCompiler` already handles this with PSR-4 fallback. Verify in research.md Q2. |
| Cross-package integration test fragility (Success Criterion 5) | Medium | Use `DBALDatabase::createSqlite()` + `InMemoryEntityStorage` only where the test is exercising a non-storage concern; otherwise SQLite for fidelity. |

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| (none) | | |

No charter violations to track. The mission scope is large but each primitive lives in its natural-home package with no exemptions, no shims, no parallel registries.

## Branch Strategy

- **Planning base**: `main`
- **Final merge target**: `main`
- Lane execution worktrees are computed by `spec-kitty agent mission finalize-tasks` after `/spec-kitty.tasks`.
