# Tasks: Single-Entity Work Surface

**Mission**: `single-entity-work-surface-01KQ7M1P`
**Spec**: [spec.md](spec.md) · **Plan**: [plan.md](plan.md) · **Research**: [research.md](research.md) · **Data Model**: [data-model.md](data-model.md) · **Contracts**: [contracts/README.md](contracts/README.md) · **Quickstart**: [quickstart.md](quickstart.md)
**Branch contract**: planning base `main` → merge target `main`

## Subtask Index

| ID | Description | WP | Parallel |
|---|---|---|---|
| T001 | Add `group` and `promptAliases` parameters to `FieldDefinition` constructor | WP01 | — | [D] |
| T002 | Add `getGroup()` and `getPromptAliases()` to `FieldDefinitionInterface` and implement in `FieldDefinition` | WP01 | — | [D] |
| T003 | Update `FieldDefinitionTest` to cover the two new constructor parameters and getters | WP01 | — | [D] |
| T004 | Write `UPGRADING.md` entry explaining the constructor signature change with named-arg migration recipe | WP01 | [D] |
| T005 | Add `BundleTemplate` class-level attribute in `packages/field/src/Attribute/BundleTemplate.php` | WP02 | — | [D] |
| T006 | Add `FieldTemplate` repeatable attribute in `packages/field/src/Attribute/FieldTemplate.php` | WP02 | [D] |
| T007 | Implement `BundleTemplateCompiler` (attribute discovery via `PackageManifestCompiler` → `FieldDefinitionRegistry::registerBundleFields()`) | WP02 | — | [D] |
| T008 | Wire `BundleTemplateCompiler` to run at boot (new `FieldServiceProvider` or hook into existing field registration path) | WP02 | — | [D] |
| T009 | Unit-test `BundleTemplateCompiler`: discovery, ordering, alias-uniqueness validation, key-uniqueness validation | WP02 | — | [D] |
| T010 | Integration test: declared `#[BundleTemplate]` + `#[FieldTemplate]` classes produce expected registry contents | WP02 | — | [D] |
| T011 | Add `FormFieldDescriptor` readonly value object in `packages/field/src/Form/FormFieldDescriptor.php` | WP03 | — | [D] |
| T012 | Implement `FormDescriptorBuilder::build()` in `packages/field/src/Form/FormDescriptorBuilder.php` (registry walk, value extraction, `readOnly` resolution via `FieldAccessPolicyInterface`) | WP03 | — | [D] |
| T013 | Unit-test `FormFieldDescriptor` constructor, immutability, defaults | WP03 | [D] |
| T014 | Unit-test `FormDescriptorBuilder`: ordering, value extraction, `readOnly` from `FieldDefinition` and from `FieldAccessPolicyInterface`, ungrouped + grouped fields, missing entity values | WP03 | — | [D] |
| T015 | Add `EntityDeepLinkRouteBuilder` in `packages/routing/src/EntityDeepLinkRouteBuilder.php` (composes `RouteBuilder::create()` + `entityParameter()`) | WP04 | — |
| T016 | Unit-test `EntityDeepLinkRouteBuilder::for()->controller()` produces a Symfony `Route` with the expected path, method, parameter resolver, and option flags | WP04 | — |
| T017 | Integration test: deep-link route resolves entity via `EntityRepository` and runs access policy before invoking controller; 404 on missing entity; 403 on access denied | WP04 | — |
| T018 | Author-side example in routing docstring showing the `for(...)->controller(...)->methods(...)->build()` chain pattern | WP04 | [P] |
| T019 | Create `packages/attachment/composer.json` declaring the package at L2 with deps on entity, entity-storage, access, foundation | WP05 | — |
| T020 | Implement `Attachment` content entity (extends `ContentEntityBase`) with required entity keys | WP05 | — |
| T021 | Implement `AttachmentSchema` for `SqlSchemaHandler` (columns, indexes, `_data` blob fields per data-model.md § 1) | WP05 | — |
| T022 | Implement `AttachmentRepository` with `listFor`, `getActive`, `save`, `delete` methods backed by `EntityRepositoryInterface` + `DatabaseInterface` | WP05 | — |
| T023 | Implement `AttachmentRepository::setActive()` using direct `DatabaseInterface::transaction()` with two UPDATE statements per research.md Q6 | WP05 | — |
| T024 | Wire `AttachmentRepository` and entity-type registration in `packages/attachment/src/ServiceProvider.php`; unit-test `AttachmentRepository` against SQLite | WP05 | — |
| T025 | Implement `ParentDelegatedAccessPolicy` in `packages/attachment/src/Policy/ParentDelegatedAccessPolicy.php` with `#[PolicyAttribute(entityType: 'attachment')]` | WP06 | — |
| T026 | Unit-test `ParentDelegatedAccessPolicy`: view delegates to parent, update delegates to parent, returns Neutral if parent missing | WP06 | — |
| T027 | Concurrency test: 50 concurrent `setActive` calls against the same parent leave exactly one active attachment (NFR-010) | WP06 | — |
| T028 | Add to `packages/attachment/tests/Integration/`: `setActive` invariant, parent-delegated access end-to-end | WP06 | — |
| T029 | Implement `FieldAutoSaveController::update()` in `packages/api/src/Controller/FieldAutoSaveController.php` (load entity, validate field key against registry, run access policies, persist via `EntityRepository`, return JSON:API-shaped response) | WP07 | — |
| T030 | Implement body-size guard producing 422 without buffering full payload (NFR-002) and content-type negotiation producing 415 for non-`application/json` requests | WP07 | — |
| T031 | Edit `packages/api/src/JsonApiRouteProvider.php` to register `PUT {basePath}/{entityType}/{id}/field/{key}` for every entity type via the existing iteration loop | WP07 | — |
| T032 | Add `PayloadTooLargeException` (or extend existing `ApiException` if present) in `packages/api/src/Exception/` | WP07 | [P] |
| T033 | Integration test: PUT happy path, 401, 403 (entity policy), 403 (field policy), 404 (entity), 404 (field key), 415, 422 (oversize), 422 (malformed), idempotency | WP07 | — |
| T034 | Create `packages/structured-import/composer.json` declaring the package at L3 with deps on field, foundation | WP08 | — |
| T035 | Add `StructuredImporterInterface` in `packages/structured-import/src/StructuredImporterInterface.php` per contracts/README.md F5 | WP08 | — |
| T036 | Add `ImportResult` and `UnmatchedRow` readonly value objects | WP08 | [P] |
| T037 | Implement `GfmTableParser` in `packages/structured-import/src/Gfm/GfmTableParser.php` per research.md Q7 (pipe-delimited, optional leading/trailing pipe, header separator required, escaped pipes, exactly 2 columns) | WP08 | — |
| T038 | Unit-test `GfmTableParser`: happy path, optional pipes, escaped pipes, alignment markers ignored, multi-table doc (first parsed, rest in errors), 3+ column row error, missing header separator error, multi-line cell error | WP08 | — |
| T039 | Wire structured-import package via `packages/structured-import/src/ServiceProvider.php` | WP08 | [P] |
| T040 | Implement `PromptNormalizer::normalize()` per research.md Q8 (`mb_strtolower` + Unicode whitespace collapse + trim) | WP09 | [P] |
| T041 | Implement `GfmTableImporter::import()` (calls parser, normalizes prompts, looks up `FieldDefinition::getPromptAliases()` from registry, builds `ImportResult`) | WP09 | — |
| T042 | Unit-test `GfmTableImporter`: matched, unmatched, errors paths; alias normalization; bundle param handling (null fallback to entity_type as implicit single bundle) | WP09 | — |
| T043 | Contract test (`Contract/StructuredImporterContractTest.php`) — abstract base verifying any `StructuredImporterInterface` implementation respects the contract | WP09 | — |
| T044 | End-to-end integration test in `tests/Integration/Phase##/SingleEntityWorkSurfaceTest.php` exercising all six primitives in one method (Success Criterion 5) | WP10 | — |
| T045 | Create `docs/specs/work-surface.md` capturing the six-primitive subsystem; update orchestration table in `CLAUDE.md` to include the new spec | WP10 | — |
| T046 | Update `docs/specs/entity-system.md`, `docs/specs/api-layer.md`, `docs/specs/access-control.md` for the additions (FieldDefinition extension, auto-save endpoint, parent-delegated policy pattern) | WP10 | [P] |
| T047 | Add CHANGELOG.md entries (Added: F1/F2/F3/F4/F5/F6 features, two new packages; Changed: `FieldDefinition` constructor breaking change with cross-reference to UPGRADING.md) | WP10 | [P] |
| T048 | Update root `composer.json` to add `@dev` constraints for `waaseyaa/attachment` and `waaseyaa/structured-import`; update meta-packages (`waaseyaa/full`) if appropriate | WP10 | — |
| T049 | Run `bin/check-package-layers`, `bin/check-composer-policy`, `composer phpstan`, `composer cs-check`, full PHPUnit suite — all must pass before WP10 review | WP10 | — |

## Work Packages

### WP01 — Foundation: enrich `FieldDefinition`

**Goal**: Add `group: string` and `promptAliases: list<string>` to `Waaseyaa\Field\FieldDefinition` (and its interface) so F2's compiler can emit fully-described field definitions and F5's importer can match prompts. Author the UPGRADING.md migration recipe for the breaking constructor change.

**Priority**: P0 (blocks WP02, WP03, WP07, WP09).

**Dependencies**: none.

**Independent test**:
1. `new FieldDefinition(name: 'x', type: 'string', group: 'g', promptAliases: ['x', 'X'])` constructs without error.
2. Existing test suite (`packages/field/tests/Unit/FieldDefinitionTest.php`) passes after edit.
3. `composer phpstan` (level 5) passes for `packages/field/`.

**Included subtasks**:
- [x] T001 Add `group` and `promptAliases` parameters to FieldDefinition constructor (WP01)
- [x] T002 Add `getGroup()` and `getPromptAliases()` to interface and class (WP01)
- [x] T003 Update `FieldDefinitionTest` for new params (WP01)
- [x] T004 Write `UPGRADING.md` entry (WP01)

**Implementation sketch**: see `kitty-specs/single-entity-work-surface-01KQ7M1P/tasks/WP01-foundation-field-definition.md` for the WP-level prompt.

**Parallel opportunities**: T004 (UPGRADING.md) can run in parallel with T001/T002 since it touches a different file.

**Risks**: cascading constructor-call failures in other Waaseyaa packages that use positional arguments to `FieldDefinition`. UPGRADING.md call-out + named-arg refactor in WP02+ catches them. Per DIR-003 we accept the breaking change and update callers in the same change set.

**Estimated prompt size**: ~280 lines.

---

### WP02 — F2: Bundle/Field-template attributes + compiler

**Goal**: Ship the attribute-driven bundle template registry. Add `BundleTemplate` (class) and `FieldTemplate` (property/method, repeatable) attributes; implement `BundleTemplateCompiler` that scans via `PackageManifestCompiler` and registers fields with `FieldDefinitionRegistry::registerBundleFields()`. Wire the compiler at boot.

**Priority**: P0 (blocks WP07, WP09, WP10).

**Dependencies**: WP01.

**Independent test**:
1. A test fixture class with `#[BundleTemplate(entityType: 'node', bundle: 'profile')]` and three `#[FieldTemplate]` properties produces three `FieldDefinition` instances in `FieldDefinitionRegistry::bundleFieldsFor('node', 'profile')` in declaration order.
2. Each `FieldDefinition` carries the declared `label`, `group`, `promptAliases`, `required`, `readOnly`.
3. Duplicate `key` within a bundle throws `\InvalidArgumentException` at compile time.
4. Duplicate normalized `promptAlias` within a bundle throws at compile time.

**Included subtasks**:
- [x] T005 Add `BundleTemplate` attribute (WP02)
- [x] T006 Add `FieldTemplate` attribute (WP02)
- [x] T007 Implement `BundleTemplateCompiler` (WP02)
- [x] T008 Wire compiler at boot (WP02)
- [x] T009 Unit-test `BundleTemplateCompiler` (WP02)
- [x] T010 Integration test: declared template → registry contents (WP02)

**Implementation sketch**: see WP02 prompt.

**Parallel opportunities**: T005 and T006 are different files, parallelizable. T009 follows T007.

**Risks**: attribute scanning at boot may break in non-classmap environments — research.md Q2 confirms `PackageManifestCompiler` already handles PSR-4 fallback. Compiler must be idempotent (safe to call twice in dev with cache misses).

**Estimated prompt size**: ~420 lines.

---

### WP03 — F6: FormFieldDescriptor + FormDescriptorBuilder

**Goal**: Ship the schema-driven form descriptor builder. Add `FormFieldDescriptor` value object and `FormDescriptorBuilder::build($entity, $bundle, ?$account)` that walks `FieldDefinitionRegistry::bundleFieldsFor()` and emits `FormFieldDescriptor[]`. No HTML, no Twig, structured arrays only (Q2: A).

**Priority**: P1 (blocks WP10).

**Dependencies**: WP01.

**Independent test**:
1. `FormDescriptorBuilder::build($entity, 'profile')` returns `FormFieldDescriptor[]` in declared order.
2. Each descriptor's `value` matches `EntityInterface::get($name)`.
3. `readOnly` is `true` iff `FieldDefinition::isReadOnly()` OR (`accessHandler` non-null AND `fieldAccess('update', $name)` returns `Forbidden`).
4. `label` defaults to `ucfirst($name)` when empty.
5. Empty bundle returns empty array (no exception).

**Included subtasks**:
- [x] T011 Add `FormFieldDescriptor` (WP03)
- [x] T012 Implement `FormDescriptorBuilder::build()` (WP03)
- [x] T013 Unit-test `FormFieldDescriptor` (WP03)
- [x] T014 Unit-test `FormDescriptorBuilder` (WP03)

**Implementation sketch**: see WP03 prompt.

**Parallel opportunities**: T011 + T013 (descriptor work) can run alongside T012 + T014 (builder work) in different files.

**Risks**: `FieldAccessPolicyInterface` resolution requires the entity's policy be discoverable; verify access discovery works for content entity types in tests.

**Estimated prompt size**: ~310 lines.

---

### WP04 — F1: Entity deep-link route helper

**Goal**: Add `EntityDeepLinkRouteBuilder` that composes existing `RouteBuilder::create()` and `entityParameter()` to register `/<segment>/<entity_type>/{id}` routes with one declarative call. No edits to existing routing classes (research.md Q3).

**Priority**: P1 (blocks WP10).

**Dependencies**: none.

**Independent test**:
1. `EntityDeepLinkRouteBuilder::for('/edit', 'node')->controller('App\Foo::view')->build()` returns a Symfony `Route` with path `/edit/node/{id}`, GET method, and entity parameter resolver for `node`.
2. Hitting `/edit/node/<missing-id>` produces a 404 response without invoking the controller.
3. Hitting `/edit/node/<existing-id>` invokes the controller with the hydrated entity.
4. Route `_gate` access option enforces `AccessPolicyInterface::access('view')` before controller invocation.

**Included subtasks**:
- [ ] T015 Add `EntityDeepLinkRouteBuilder` class (WP04)
- [ ] T016 Unit-test the builder produces expected `Route` configuration (WP04)
- [ ] T017 Integration test: deep-link route resolution + access enforcement (WP04)
- [ ] T018 Author-side docstring example (WP04)

**Implementation sketch**: see WP04 prompt.

**Parallel opportunities**: T018 (docstring) can run in parallel with T016/T017.

**Risks**: integration test must run against a real `WaaseyaaRouter` + `EntityParamConverter`; existing test infrastructure should support this.

**Estimated prompt size**: ~250 lines.

---

### WP05 — F4a: Attachment package skeleton + repository

**Goal**: Ship the new `waaseyaa/attachment` package at L2: `composer.json`, `Attachment` entity, schema, repository (`listFor`, `getActive`, `setActive`, `save`, `delete`), service provider. The atomic `setActive` uses direct DB transaction per research.md Q6.

**Priority**: P1 (blocks WP06, WP10).

**Dependencies**: none.

**Independent test**:
1. `AttachmentRepository::save(...)` persists an `Attachment` retrievable via `EntityRepository::find()`.
2. `listFor('node', '42')` returns all attachments for that parent in insertion order.
3. `setActive($id)` flips `is_active` to true on the target and false on all siblings of the same parent in one transaction.
4. `getActive('node', '42')` returns the unique active attachment, or null.
5. `bin/check-package-layers` passes — `attachment` declares deps only on L0–L1.

**Included subtasks**:
- [ ] T019 Create package `composer.json` (WP05)
- [ ] T020 Implement `Attachment` entity (WP05)
- [ ] T021 Implement `AttachmentSchema` (WP05)
- [ ] T022 Implement `AttachmentRepository` core methods (WP05)
- [ ] T023 Implement `AttachmentRepository::setActive()` with transaction (WP05)
- [ ] T024 Wire ServiceProvider + repository unit tests (WP05)

**Implementation sketch**: see WP05 prompt.

**Parallel opportunities**: T019 must come first (composer.json bootstraps autoload). T020 + T021 are distinct files. T022 + T023 are sequential (T023 builds on T022).

**Risks**: `setActive` UPDATE statement uses `DatabaseInterface` query builder; verify the `update()` builder supports the WHERE clauses needed. If not, fall back to raw SQL via `DBALDatabase::getConnection()` (only acceptable for the attachment package's internal use; not promoted to a pattern).

**Estimated prompt size**: ~480 lines.

---

### WP06 — F4b: Attachment policy + concurrency test

**Goal**: Ship `ParentDelegatedAccessPolicy` (delegates view/update to the parent entity's policy) with `#[PolicyAttribute(entityType: 'attachment')]` for auto-discovery. Add NFR-010 concurrency test for `setActive` invariant.

**Priority**: P1 (blocks WP10).

**Dependencies**: WP05.

**Independent test**:
1. A user who can `view` the parent `node` can `view` its attachments; one who can `update` the parent can `update`/`setActive`/`delete` its attachments.
2. With parent missing (referential integrity gap), policy returns `Neutral` (handler then denies via `isAllowed()`).
3. Under 50 concurrent `setActive` calls against the same parent, exactly one row has `is_active = true` post-test.

**Included subtasks**:
- [ ] T025 Implement `ParentDelegatedAccessPolicy` (WP06)
- [ ] T026 Unit-test policy: view/update delegation, missing parent (WP06)
- [ ] T027 Concurrency test for `setActive` invariant (NFR-010) (WP06)
- [ ] T028 Integration tests for parent-delegated access + setActive end-to-end (WP06)

**Implementation sketch**: see WP06 prompt.

**Parallel opportunities**: T025 and T027 are different files; T026 + T028 follow.

**Risks**: concurrency test on Windows may be flaky if PHP threads can't truly parallelize. Use process-fork or pcntl-style or simulate via tight loop with explicit transaction interleaving — verify approach works in CI matrix (Ubuntu/Debian).

**Estimated prompt size**: ~340 lines.

---

### WP07 — F3: Per-field auto-save endpoint

**Goal**: Ship `FieldAutoSaveController` and register `PUT /api/{entityType}/{id}/field/{key}` for every entity type via `JsonApiRouteProvider`. Enforce entity policy + field policy, idempotent semantics, 64 KiB body cap.

**Priority**: P1 (blocks WP10).

**Dependencies**: WP01, WP02.

**Independent test**:
1. PUT happy path returns 200 with `{"data": {...}}`.
2. 401, 403 (entity policy), 403 (field policy), 404 (entity), 404 (field key), 415 (wrong content type), 422 (oversize body), 422 (malformed body) all behave per contracts/README.md F3.
3. Two identical PUTs converge — second produces same persisted state, no entity-state divergence.
4. p95 latency ≤ 50 ms server-side under nominal load against SQLite.

**Included subtasks**:
- [ ] T029 Implement `FieldAutoSaveController::update()` (WP07)
- [ ] T030 Body-size guard + content-type negotiation (WP07)
- [ ] T031 Edit `JsonApiRouteProvider` to register the route (WP07)
- [ ] T032 Add `PayloadTooLargeException` (WP07)
- [ ] T033 Integration test exercising all status codes + idempotency (WP07)

**Implementation sketch**: see WP07 prompt.

**Parallel opportunities**: T032 (exception class) is independent; T033 follows T029-T031.

**Risks**: body-size guard must run before full body buffering. Symfony's `Request` object reads the body lazily, but `getContent()` consumes it. Use `Content-Length` header check first; `php://input` cap via `stream_get_contents` with a length limit if header missing.

**Estimated prompt size**: ~430 lines.

---

### WP08 — F5a: Structured-import package skeleton + GFM parser

**Goal**: Ship the new `waaseyaa/structured-import` package at L3: `composer.json`, `StructuredImporterInterface`, `ImportResult`, `UnmatchedRow`, `GfmTableParser` (in-house, no CommonMark dep). Parser supports the subset in research.md Q7.

**Priority**: P1 (blocks WP09).

**Dependencies**: none.

**Independent test**:
1. `GfmTableParser::parse()` on a 2-column table returns `[['prompt'=>'Title','value'=>'Hello'], ...]`.
2. Unsupported syntax (3+ columns, missing separator, multi-line cells) is recorded in `errors`, parsing continues for valid rows.
3. Multiple 2-column tables: first parsed, subsequent recorded in errors.
4. Escaped pipes (`\|`) preserved as literal `|` in cell content.
5. `bin/check-package-layers` passes — `structured-import` declares deps only on L0–L2.

**Included subtasks**:
- [ ] T034 Create package `composer.json` (WP08)
- [ ] T035 Add `StructuredImporterInterface` (WP08)
- [ ] T036 Add `ImportResult` and `UnmatchedRow` value objects (WP08)
- [ ] T037 Implement `GfmTableParser` (WP08)
- [ ] T038 Unit-test `GfmTableParser` (WP08)
- [ ] T039 Wire ServiceProvider (WP08)

**Implementation sketch**: see WP08 prompt.

**Parallel opportunities**: T035, T036, T039 in distinct files.

**Risks**: parser scope creep — keep to the subset in research.md Q7. Reject unsupported syntax explicitly via `errors`, never silently.

**Estimated prompt size**: ~440 lines.

---

### WP09 — F5b: GfmTableImporter + prompt matching + contract test

**Goal**: Implement `GfmTableImporter` that wires `GfmTableParser` + `PromptNormalizer` + `FieldDefinitionRegistry` to produce `ImportResult` with matched/unmatched/errors. Add the `StructuredImporterContractTest` abstract base.

**Priority**: P1 (blocks WP10).

**Dependencies**: WP01, WP02, WP08.

**Independent test**:
1. Document with prompts matching declared aliases (after normalization) lands in `matched` keyed by field name.
2. Unknown prompt lands in `unmatched`.
3. Empty document: `errors = ["No table found"]`.
4. Bundle param defaults to entity_type when null and the entity type has no bundle entity type.
5. Contract test passes against `GfmTableImporter`; another importer (e.g., a stub `JsonImporter` mock) also satisfies the contract via the abstract base.

**Included subtasks**:
- [ ] T040 Implement `PromptNormalizer::normalize()` (WP09)
- [ ] T041 Implement `GfmTableImporter::import()` (WP09)
- [ ] T042 Unit-test `GfmTableImporter` (WP09)
- [ ] T043 Contract test for `StructuredImporterInterface` (WP09)

**Implementation sketch**: see WP09 prompt.

**Parallel opportunities**: T040 (normalizer) is independent; T042 + T043 follow T041.

**Risks**: prompt normalization must be applied symmetrically — same function on declared aliases (cached at compile time, ideally) and inbound prompts. Validate at compile time vs at match time? Choose compile time (cache normalized aliases) to keep import-time fast.

**Estimated prompt size**: ~360 lines.

---

### WP10 — Cross-cutting: end-to-end test + docs + release notes

**Goal**: Ship the cross-primitive integration test (Success Criterion 5), spec documentation for the new subsystem, CHANGELOG entries for the entire mission, and metapackage updates so consumers can install all primitives via `waaseyaa/full`.

**Priority**: P1 (final WP).

**Dependencies**: WP02, WP03, WP04, WP06, WP07, WP09.

**Independent test**:
1. Single PHPUnit method exercises all six primitives end-to-end against `DBALDatabase::createSqlite()`. Passes in < 2 seconds.
2. `bin/check-package-layers`, `bin/check-composer-policy`, `composer phpstan`, `composer cs-check`, full PHPUnit suite all pass on `main`.
3. New `docs/specs/work-surface.md` exists; orchestration table in `CLAUDE.md` references it.
4. `CHANGELOG.md` has Added/Changed entries for the mission under `## [Unreleased]`.
5. Root `composer.json` includes `waaseyaa/attachment` and `waaseyaa/structured-import` under `require-dev` or `require` per project convention; `waaseyaa/full` metapackage references both.

**Included subtasks**:
- [ ] T044 End-to-end integration test (WP10)
- [ ] T045 Create `docs/specs/work-surface.md` + `CLAUDE.md` orchestration entry (WP10)
- [ ] T046 Update existing specs (`entity-system.md`, `api-layer.md`, `access-control.md`) (WP10)
- [ ] T047 CHANGELOG entries (WP10)
- [ ] T048 Root `composer.json` + metapackage updates (WP10)
- [ ] T049 Run all gate scripts; fix any failures before requesting review (WP10)

**Implementation sketch**: see WP10 prompt.

**Parallel opportunities**: T046 + T047 + T048 are distinct files.

**Risks**: T049 may surface latent issues in earlier WPs that passed isolated review. If gate failures appear here, file them as feedback against the responsible WP — do not paper over in WP10.

**Estimated prompt size**: ~390 lines.

## Lane plan (suggested; finalize-tasks computes the actual lanes)

```
Lane A (foundation chain):
  WP01 → WP02 → WP07 (also depends on WP01 + WP02)
  WP01 → WP02 → WP09 (also depends on WP08)
  WP01 → WP03

Lane B (attachment chain):
  WP05 → WP06

Lane C (importer parser):
  WP08 → (joins Lane A at WP09)

Lane D (routing):
  WP04 (independent)

Lane E (cross-cutting):
  WP10 (depends on WP02, WP03, WP04, WP06, WP07, WP09)
```

WP04, WP05, WP08 can start in parallel from `main` (no dependencies). After WP01 lands, WP02/WP03 unlock. After WP02, WP07 and WP09 unlock (with WP08 also feeding WP09). After WP06 lands, the attachment chain is done. WP10 collapses everything.

## MVP scope

WP01 + WP02 + WP07 + WP10 (subset of WP10's tests covering F1–F3 only) gives a minimal "deep-linkable entity workspace with auto-save and bundle-driven field discovery" — useful even without attachments and import. But the spec defines the mission as all six primitives, so MVP for *this mission* is "all WPs through WP10 complete."

## Estimated work-package sizing

| WP | Subtasks | Est. lines | Within target? |
|---|---|---|---|
| WP01 | 4 | ~280 | ✓ |
| WP02 | 6 | ~420 | ✓ |
| WP03 | 4 | ~310 | ✓ |
| WP04 | 4 | ~250 | ✓ |
| WP05 | 6 | ~480 | ✓ (upper end) |
| WP06 | 4 | ~340 | ✓ |
| WP07 | 5 | ~430 | ✓ |
| WP08 | 6 | ~440 | ✓ |
| WP09 | 4 | ~360 | ✓ |
| WP10 | 6 | ~390 | ✓ |

All within the 200–500 line ideal range. No WP exceeds the 700-line hard limit. Total: 49 subtasks across 10 WPs.
