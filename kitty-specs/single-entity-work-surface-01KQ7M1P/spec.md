# Specification: Single-Entity Work Surface

**Mission ID**: 01KQ7M1PHWD8QAQPJC91RAVE0T
**Mission slug**: single-entity-work-surface-01KQ7M1P
**Mission type**: software-dev
**Target branch**: main
**Created**: 2026-04-27

## Overview

Provide a reusable capability bundle that lets downstream applications drop in a deep-linkable, schema-driven workspace for editing one entity at a time — without hand-rolling routes, persistence wiring, or per-field auto-save controllers. The bundle exposes six composable primitives consumed through existing Waaseyaa patterns (`ContentEntityBase` + PHP 8.4 attributes, `EntityRepository`, `WaaseyaaRouter`/`RouteBuilder`, `AccessPolicyInterface`, `ServiceProvider`).

The primitives are split across natural homes by layer (option B):

| Primitive | Layer | Home |
|---|---|---|
| F1 — Entity deep-link routing | L4 | extend `waaseyaa/routing` |
| F2 — Bundle-keyed field-template registry | L1 | extend `waaseyaa/field` |
| F3 — Per-field auto-save endpoint | L4 | extend `waaseyaa/api` |
| F4 — Attachment with active-reference | L2 | new `waaseyaa/attachment` |
| F5 — Structured-import pipeline | L3 | new `waaseyaa/structured-import` |
| F6 — Schema-driven form renderer | L1 | extend `waaseyaa/field` |

## User Scenarios & Testing

### Primary user: a downstream application developer ("consumer")

#### Scenario 1 — Deep-link an entity workspace

Consumer declares a deep-link route segment (e.g. `/edit`) and target entity type (`node`). End user visits `/edit/node/42`. Framework resolves entity `42` via `EntityRepository`, runs `AccessPolicyInterface`, dispatches consumer's controller with the hydrated entity or returns 404. **Acceptance**: One declaration, no hand-rolled lookup or access wiring.

#### Scenario 2 — Register a field template for a bundle

Consumer attaches PHP 8.4 attributes to a class declaring ordered fields with `key`, `label`, optional `group`, optional `prompt_aliases`. The registry compiles these at boot and feeds both F5's importer and F6's form renderer. **Acceptance**: One declaration drives both form and importer; no duplicate config.

#### Scenario 3 — Auto-save a single field

End user edits one field; client PUTs `/<api>/{entityType}/{id}/field/{key}` with `{"value": "..."}`. Framework loads entity, runs entity + field policies, persists, returns 200. Idempotent. **Acceptance**: 200 on success; 401/403 on access denied; 404 on missing entity or unregistered field; 422 on oversize body.

#### Scenario 4 — Attach files with an active selection

End user uploads N files to a parent entity. `setActive($attachmentId)` flips active flag on chosen attachment and clears it on siblings atomically. `getActive($parentId)` returns at most one. Default policy delegates view/edit decisions to the parent entity's policy. **Acceptance**: Concurrent `setActive` invariant holds; access decisions never diverge from parent.

#### Scenario 5 — Import a markdown table

End user uploads a GFM document with a 2-column table. Default importer parses, normalizes prompts (case-fold, collapse whitespace, trim), matches against F2's `prompt_aliases`, returns `{matched, unmatched, errors}`. **Acceptance**: Known prompts → matched; unknown → unmatched; malformed → descriptive errors.

#### Scenario 6 — Render a bundle form

Consumer asks F6 for a form representation of `(node, article)`. F6 walks F2's registry, emits structured array (or HTML) with one entry per field in declared order, grouped, marked read-only when `FieldAccessPolicyInterface` returns `Forbidden`, pre-populated from entity values. **Acceptance**: Adding a field via attribute appears in form on next boot without template edits.

### Edge cases

- Unknown entity type in F1: 404, not 500.
- Field key not in registry for F3: 404 with `field_key` in error body.
- Oversize body in F3: 422 with size cap echoed; framework must not buffer full body.
- Concurrent `setActive` in F4: `UnitOfWork` or DB transaction ensures invariant.
- Empty markdown in F5: returns `errors: ["No table found"]`, not exception.
- Multiple tables in F5: first parsed, rest in errors as warnings.
- Bundle with no fields in F6: empty representation, not error.

## Functional Requirements

| ID | Requirement | Status |
|---|---|---|
| FR-001 | The routing package shall expose a helper that registers a deep-link route at `/<segment>/<entity_type>/{id}`, resolves the entity through `EntityRepository`, applies the configured `AccessPolicyInterface`, and either dispatches the consumer's controller with the hydrated entity or returns the framework's 404-equivalent. | Draft |
| FR-002 | The routing helper shall compose with `RouteBuilder` chaining and `WaaseyaaRouter::addRoute` such that consumers register the deep-link route in a `ServiceProvider` `boot()` method without hand-rolled middleware. | Draft |
| FR-003 | The field package shall expose PHP 8.4 attributes (`#[BundleTemplate]`, `#[FieldTemplate]`) that declare per-bundle ordered fields with required `key`, `label`, optional `group`, optional `prompt_aliases`. | Draft |
| FR-004 | The field package shall compile attribute-declared field templates into an in-memory registry at boot time, keyed by `(entity_type, bundle)`, accessible via a single registry service registered through a `ServiceProvider`. | Draft |
| FR-005 | The api package shall expose a single controller route `PUT /<api_segment>/{entity_type}/{id}/field/{key}` accepting `{"value": string}`, persisting the field through `EntityRepository`, with idempotent semantics. | Draft |
| FR-006 | The auto-save endpoint shall enforce the entity's `AccessPolicyInterface` for `update` and, when implemented, the `FieldAccessPolicyInterface` for the targeted field, returning 401/403 when access is denied. | Draft |
| FR-007 | The auto-save endpoint shall return 404 when the entity does not exist OR when the field key is not registered for the entity's bundle in the F2 registry. | Draft |
| FR-008 | The auto-save endpoint shall return 422 when the request body exceeds the configured size cap, without buffering the full payload. | Draft |
| FR-009 | A new `waaseyaa/attachment` package shall define an `Attachment` content entity (extends `ContentEntityBase`) with parent-entity reference fields (`parent_entity_type`, `parent_entity_id`) and a boolean `is_active` flag. | Draft |
| FR-010 | The attachment repository shall expose `listFor(parentEntityType, parentId)`, `setActive(attachmentId)` (clears active on sibling attachments atomically), and `getActive(parentEntityType, parentId)` returning at most one entity. | Draft |
| FR-011 | The attachment package shall ship a default `AccessPolicyInterface` implementation that delegates view/edit decisions to the parent entity's policy, registered via `#[PolicyAttribute]`. | Draft |
| FR-012 | A new `waaseyaa/structured-import` package shall define a `StructuredImporterInterface` taking an upload payload + `(entity_type, bundle)` and returning a result object with `matched: array<string,string>`, `unmatched: array<{prompt:string, value:string}>`, `errors: array<string>`. | Draft |
| FR-013 | The structured-import package shall ship a default `GfmTableImporter` that parses a 2-column GFM table (left=prompt, right=value), with prompt-matching that case-folds, collapses internal whitespace, and trims, comparing against F2's `prompt_aliases`. | Draft |
| FR-014 | The default GFM table parser shall be implemented in-house (no `league/commonmark` or comparable runtime dependency added) and live in the structured-import package. | Draft |
| FR-015 | The field package shall expose a renderer that, given an entity and bundle, produces a structured array of form-field descriptors derived from F2's registry, in declared order, with values pre-populated from the entity, fields marked read-only when `FieldAccessPolicyInterface` returns `Forbidden` on `update`, and grouped by optional `group`. | Draft |
| FR-016 | All new services (registry, importer, attachment repository, route helper, auto-save controller, renderer) shall be registered through `ServiceProvider::register()` / `boot()` with no static state, no facades, and no global service locators. | Draft |
| FR-017 | All entity reads and writes shall flow through `EntityRepository`/`SqlStorageDriver`; no raw PDO, no ActiveRecord. | Draft |
| FR-018 | The auto-save endpoint and attachment endpoints shall accept `application/json` and reject other content types with 415. | Draft |
| FR-019 | The attachment package shall enforce its "at most one active per parent" invariant under concurrent writes via a single transaction (or `UnitOfWork`) boundary in `setActive`. | Draft |
| FR-020 | The structured-import default importer shall return errors (not throw) for malformed tables, missing tables, or rows with the wrong column count, using human-readable strings safe to surface in admin UIs. | Draft |

## Non-Functional Requirements

| ID | Requirement | Threshold | Status |
|---|---|---|---|
| NFR-001 | Auto-save endpoint latency under nominal load (single field, < 4 KiB body, SQLite backend) | p95 ≤ 50 ms server-side from request received to response sent, measured on the project's existing integration test harness | Draft |
| NFR-002 | Auto-save body size cap | Default 64 KiB; configurable per route option; enforced before full body is read | Draft |
| NFR-003 | Field-template registry boot cost | Registry compilation adds ≤ 5 ms to kernel boot for a project with 100 bundle templates totaling 1,000 field templates | Draft |
| NFR-004 | Structured-import parser memory | Parser must process documents up to 1 MiB without loading more than 2× document size into memory | Draft |
| NFR-005 | Layer compliance | `bin/check-package-layers` passes after the change with zero exemptions added | Draft |
| NFR-006 | Composer policy | `bin/check-composer-policy` passes; no `@dev` or wildcard internal constraints introduced outside root | Draft |
| NFR-007 | Test coverage for new packages | Each new package (`attachment`, `structured-import`) ships unit + integration tests; cross-package integration tests cover F1+F3+F4 in `tests/Integration/PhaseN/` | Draft |
| NFR-008 | No new runtime dependencies | New packages declare no Composer `require` entries beyond first-party `waaseyaa/*` and Symfony components already used in the monorepo, unless justified in the plan | Draft |
| NFR-009 | PHP version | All new code declares `strict_types=1` and uses PHP 8.4 features where they improve clarity (asymmetric visibility, property hooks where appropriate); no PHP 8.3-or-earlier-only fallbacks | Draft |
| NFR-010 | Attachment `setActive` correctness under concurrency | Property-style or fixture-driven test demonstrates that 50 concurrent `setActive` calls against the same parent leave exactly one active attachment | Draft |

## Constraints

| ID | Constraint | Status |
|---|---|---|
| C-001 | F1 and F3 land in `waaseyaa/routing` (L4) and `waaseyaa/api` (L4) respectively; they may import from L0–L3 only. | Active |
| C-002 | F2 and F6 land in `waaseyaa/field` (L1); they may import from L0–L1 only. The renderer must not depend on `waaseyaa/api`, `waaseyaa/admin`, or any L2+ package. | Active |
| C-003 | F4 lands in a new `waaseyaa/attachment` package at L2; may import from L0–L1 only. | Active |
| C-004 | F5 lands in a new `waaseyaa/structured-import` package at L3; may import from L0–L2 only. | Active |
| C-005 | No raw PDO, no facades, no static-state singletons, no service locators. All wiring via `ServiceProvider`. | Active |
| C-006 | All persistence flows through `EntityRepository` + `SqlStorageDriver`. The attachment package follows the canonical entity pipeline (`.claude/rules/entity-storage-invariant.md`). | Active |
| C-007 | The GFM table parser is implemented in-house; no CommonMark library added to runtime dependencies. | Active |
| C-008 | F2 attribute scanning uses `PackageManifestCompiler`-style discovery; no runtime reflection on every request. | Active |
| C-009 | `bin/check-package-layers`, `bin/check-composer-policy`, `composer phpstan` (level 5), `composer cs-check`, and `./vendor/bin/phpunit` must all pass before merge. | Active |
| C-010 | Per `DIR-003` (Greenfield Removal Policy, mission #5 merged), architecture quality is preferred over backward compatibility. Breaking changes to `JsonApiController`, `RouteBuilder`, `WaaseyaaRouter`, `EntityRepository`, or field/access interfaces are acceptable when they remove duplication, eliminate dual-state, or restore layer discipline. No compatibility shims, no `@deprecated` wrappers, no `Legacy*` namespaces. Breaking changes are announced via CHANGELOG.md + UPGRADING.md per DIR-001 communication discipline. | Active |
| C-011 | F4's default access policy reads the parent entity's policy via the existing `AccessPolicyInterface` resolution path (`PolicyAttribute` discovery); no parallel access machinery. | Active |
| C-012 | F2's `prompt_aliases` matching is normalization-only (case-fold + whitespace collapse + trim); no fuzzy / Levenshtein matching in the default implementation. | Active |

## Success Criteria

1. A consumer can expose a per-entity editing workspace at a deep-link URL with **one** `ServiceProvider` declaration per route, **zero** hand-written controller code, and **zero** custom middleware.
2. A consumer can declare a new field on an existing bundle by adding a single PHP attribute; the field appears in the form, is editable via auto-save, and is matchable on import without further wiring.
3. A consumer can implement file attachments for a parent entity in **under 30 lines** of consumer code (parent entity reference + service registration only); access decisions inherit from the parent automatically.
4. A consumer can accept a markdown-formatted import for any bundle without writing parser code; aliases declared on field templates are sufficient for matching.
5. End-to-end test: from a fresh project, exposing one entity at a deep-link URL, registering a 5-field bundle template, performing 5 auto-save round-trips, attaching 3 files and selecting an active one, and importing a markdown document with 5 rows succeeds in a single integration-test method exercising all six primitives in sequence.
6. Every `bin/check-*` script and `composer phpstan` / `composer cs-check` passes after the change. `tests/Integration/PhaseN/` covers the cross-primitive flow.
7. No new runtime Composer dependencies introduced.

## Key Entities

- **Attachment** (new content entity, `waaseyaa/attachment`): `id`, `uuid`, `parent_entity_type`, `parent_entity_id`, `filename`, `content_type`, `size`, `storage_uri`, `is_active`, timestamps. At most one `is_active = true` per `(parent_entity_type, parent_entity_id)`.
- **FieldTemplate** (value object, `waaseyaa/field`): `key`, `label`, optional `group`, optional `prompt_aliases: string[]`. Declared via PHP 8.4 attribute on bundle classes.
- **BundleTemplate** (value object, `waaseyaa/field`): `entity_type`, `bundle`, ordered list of `FieldTemplate`. Compiled into the registry at boot.
- **ImportResult** (value object, `waaseyaa/structured-import`): `matched: array<string,string>`, `unmatched: array<{prompt:string,value:string}>`, `errors: string[]`.

## Assumptions

- The "consumer" is an existing or new Waaseyaa-based application, not the framework's own admin SPA. Admin SPA integration is out of scope.
- Attachment file *storage* (where bytes live: filesystem, S3, etc.) is out of scope. The `storage_uri` field is an opaque string. A future mission can introduce a storage backend.
- Authentication and session management are already provided by the existing `SessionMiddleware` + `AuthorizationMiddleware` pipeline.
- The form renderer in F6 emits *field-level* descriptors. Page-level layout, CSRF tokens, submit endpoints, and client-side debouncing are the consumer's responsibility.
- "Bundle" is consumer-defined. For entity types without bundles, treat the entity_type id as the implicit single bundle.

## Dependencies

- **Existing packages**: `waaseyaa/foundation`, `waaseyaa/entity`, `waaseyaa/entity-storage`, `waaseyaa/field`, `waaseyaa/access`, `waaseyaa/routing`, `waaseyaa/api`. Mission extends `field`, `routing`, `api` and introduces two new packages (`attachment`, `structured-import`).
- **Specs**: `docs/specs/entity-system.md`, `docs/specs/access-control.md`, `docs/specs/field-access.md`, `docs/specs/api-layer.md`. Plan phase determines which require updates.
- **Out of scope dependencies**: admin SPA changes, ingestion envelope changes, GraphQL schema changes (these may follow in later missions).

## Out of Scope

- Admin SPA UI for consuming these primitives.
- File byte storage backend (filesystem, S3, etc.).
- Real-time collaboration / multi-user concurrent editing of the same field.
- Versioning / undo for auto-saved fields beyond what `EntityRepository` already provides.
- Non-GFM importers (CSV, YAML, JSON Schema). The interface accommodates them; no additional implementations ship in this mission.
- Fuzzy matching for `prompt_aliases` (e.g. Levenshtein, embeddings). Default matcher is normalization-only.
