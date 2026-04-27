# Tasks — Attribute-First Entity Definition

**Mission**: `attribute-first-entity-definition-01KQ6DXE`
**Mission ID**: `01KQ6DXEQ01S6PVPT6KF5946TA`
**Branch contract**: `main` → `main` (matches target).
**Work package count**: 9 — average ≈5 subtasks/WP.

---

## Subtask Index

| ID | Description | WP | Parallel |
|---|---|---|---|
| T001 | Create `Waaseyaa\Entity\Attribute\Field` attribute class | WP01 |  | [D] |
| T002 | Create `Waaseyaa\Entity\Attribute\FieldTypeInferrer` helper | WP01 |  | [D] |
| T003 | Unit tests for `Field` attribute (instantiation, parameters) | WP01 | [D] |
| T004 | Unit tests for `FieldTypeInferrer` (full PHP-type → field-type mapping table) | WP01 | [D] |
| T005 | Error-path tests: untyped property, unsupported type, type-conflict, unknown type id | WP01 | [D] |
| T006 | Extend `#[ContentEntityType]` with `label` and `description` parameters | WP02 |  | [D] |
| T007 | Extend `EntityClassMetadata` to carry `label`, `description`, `fields` | WP02 |  | [D] |
| T008 | Update `EntityMetadataReader::forClass()` to populate the new metadata fields | WP02 |  | [D] |
| T009 | Add `EntityMetadataReader::resolveFields()` with hierarchy walk + cache extension | WP02 |  | [D] |
| T010 | Tests for extended `ContentEntityType`, `EntityClassMetadata`, and `resolveFields()` | WP02 | [D] |
| T011 | Add `EntityType::fromClass(string $class): self` static factory | WP03 |  | [D] |
| T012 | Remove `fieldDefinitions:` parameter from `EntityType` constructor; introduce internalized `_fieldDefinitions` slot | WP03 |  | [D] |
| T013 | Delete `EntityTypeManager::assertClassMetadataMatchesEntityType()` and its call site in `registerEntityType()` | WP03 |  | [D] |
| T014 | Create `Waaseyaa\Entity\Tests\Helper\TestEntityType::stub()` test helper | WP03 |  | [D] |
| T015 | Tests for `fromClass()`: happy path, inheritance, missing attribute, errors, caching | WP03 | [D] |
| T016 | Performance benchmark test asserting NFR-001 (< 5 ms first call) and NFR-002 (< 0.1 ms cached) | WP03 | [D] |
| T017 | Migrate genealogy entity classes to `#[Field]` (4 files: GenealogyEvent, GenealogyFamily, GenealogyPerson, GenealogyTree) | WP04 |  | [D] |
| T018 | Migrate `packages/node/src/Node.php` to `#[Field]` | WP04 | [D] |
| T019 | Migrate `packages/note/src/Note.php` to `#[Field]` | WP04 | [D] |
| T020 | Migrate `packages/taxonomy/src/{Term,Vocabulary}.php` to `#[Field]` | WP04 | [D] |
| T021 | Migrate `packages/user/src/User.php` to `#[Field]` | WP04 | [D] |
| T022 | Update content-track ServiceProviders (genealogy, node, note, taxonomy, user) to call `EntityType::fromClass()` | WP04 |  | [D] |
| T023 | Migrate `packages/oidc/src/Entity/OidcClient.php` + `OidcServiceProvider.php` | WP05 | [D] |
| T024 | Migrate engagement entity class(es) + `EngagementServiceProvider.php` | WP05 | [D] |
| T025 | Migrate groups entity class(es) + `GroupsServiceProvider.php` | WP05 | [D] |
| T026 | Migrate messaging entity class(es) + `MessagingServiceProvider.php` | WP05 | [D] |
| T027 | Migrate path entity class(es) + `PathServiceProvider.php` | WP05 | [D] |
| T028 | Update `packages/cli/src/Command/MakeEntityTypeCommand.php` to emit attribute-first scaffold | WP05 |  | [D] |
| T029 | Migrate `packages/entity/tests/Unit/` fixtures to attribute-first form (3 files) | WP06 | [D] |
| T030 | Migrate `packages/entity-storage/tests/Unit/` fixtures (4 files) | WP06 | [D] |
| T031 | Migrate `packages/api/tests/` fixtures (3 files including `Fixtures/TranslatableTestEntity.php`) | WP06 | [D] |
| T032 | Migrate `packages/mcp/tests/Unit/` fixtures (5 files) | WP06 | [D] |
| T033 | Migrate `packages/graphql/tests/Unit/` fixtures (4 files) | WP06 | [D] |
| T034 | Run package-scoped phpunit for entity, entity-storage, api, mcp, graphql; verify green | WP06 |  | [D] |
| T035 | Migrate `packages/genealogy/tests/Unit/` fixtures (2 files) | WP07 | [P] |
| T036 | Migrate `packages/ssr/tests/Unit/` fixtures (2 files) | WP07 | [P] |
| T037 | Migrate `packages/testing/tests/Unit/` fixtures (2 files) | WP07 | [P] |
| T038 | Migrate remaining test fixtures: ai-vector (1), admin-surface (1), cli (1) | WP07 | [P] |
| T039 | Run package-scoped phpunit for genealogy, ssr, testing, ai-vector, admin-surface, cli; verify green | WP07 |  |
| T040 | Update `docs/specs/entity-system.md` — replace EntityType definition section with attribute-first patterns; refresh §Public Surface | WP08 |  |
| T041 | Update `CHANGELOG.md` with breaking-change note (`EntityType` constructor signature change; new `#[Field]` attribute) | WP08 | [P] |
| T042 | Update `UPGRADING.md` with migration recipe for any consumers carrying the old shape | WP08 | [P] |
| T043 | Update mission status meta and stub follow-on missions to reference M1 having merged | WP08 | [P] |
| T044 | Refresh `phpstan-baseline.neon` and run `vendor/bin/phpstan analyse` clean | WP09 |  |
| T045 | Run full `vendor/bin/phpunit` suite; resolve any residual failures | WP09 |  |
| T046 | Verify success criteria SC-001 through SC-005 with explicit grep/test assertions | WP09 |  |
| T047 | Verify NFR-001 / NFR-002 benchmarks pass on a fresh checkout | WP09 |  |

47 subtasks across 9 work packages.

---

## Dependency Graph

```
WP01 ──► WP02 ──► WP03 ──┬──► WP04 ──┬──► WP08 ──► WP09
                          │           │
                          ├──► WP05 ──┤
                          │           │
                          ├──► WP06 ──┤
                          │           │
                          └──► WP07 ──┘
```

- **WP01** is the foundation; nothing else can start without `#[Field]` existing.
- **WP02** extends the metadata-reading machinery; depends on WP01 (so tests can use the attribute).
- **WP03** ships the public API change (`fromClass()`, removed `fieldDefinitions:` param); depends on WP02.
- **WP04, WP05, WP06, WP07** all depend on WP03 (they consume the new API). They are mutually independent and can run in parallel.
- **WP08** (docs) depends on WP04 + WP05 (production migrations done) so the docs reflect reality.
- **WP09** is final verification and depends on everything.

---

## Work Package Details

### WP01 — `#[Field]` Attribute and Type Inferrer

**Goal**: Ship the new `#[Field]` PHP attribute and the pure helper that maps PHP property types to field-type IDs. No public API surface beyond these two new classes; no integrations.

**Priority**: P0 (foundation). MVP lives here.

**Independent test**: All WP01 unit tests pass; `Field` attribute can be instantiated; `FieldTypeInferrer::infer()` returns expected results across the full mapping table.

**Included subtasks**:
- [x] T001 Create `Waaseyaa\Entity\Attribute\Field` attribute class (WP01)
- [x] T002 Create `Waaseyaa\Entity\Attribute\FieldTypeInferrer` helper (WP01)
- [x] T003 Unit tests for `Field` attribute (WP01)
- [x] T004 Unit tests for `FieldTypeInferrer` (full mapping) (WP01)
- [x] T005 Error-path tests (WP01)

**Implementation sketch**:
1. Add `Field.php` to `packages/entity/src/Attribute/` next to existing `ContentEntityType.php`. Mirror the readonly-class style.
2. Add `FieldTypeInferrer.php` in the same directory. Pure `infer(\ReflectionProperty $p, Field $attr): array{type:string, required:bool, settings:array}`.
3. Add tests under `packages/entity/tests/Unit/Attribute/`.

**Parallel opportunities**: T003-T005 are all `[P]`-safe (different test classes per file).

**Dependencies**: None.

**Risks**:
- Attribute repeatability: spec calls for non-repeatable. Confirm `\Attribute::TARGET_PROPERTY` only, no `IS_REPEATABLE`.
- `mixed $default = null` — null is the sentinel for "no default given". Confirm semantics in tests.

**Estimated prompt size**: ~350 lines.
**Prompt**: [`tasks/WP01-field-attribute-and-inferrer.md`](./tasks/WP01-field-attribute-and-inferrer.md)

---

### WP02 — Metadata Reader Extensions

**Goal**: Extend the attribute-reading layer to surface label/description from `#[ContentEntityType]`, carry resolved field maps in `EntityClassMetadata`, and provide `EntityMetadataReader::resolveFields()` for the rest of the framework to consume.

**Priority**: P0. Sequencing dependency for WP03.

**Independent test**: `EntityClassMetadata::$fields` is populated correctly for sample test entities; `resolveFields()` walks inheritance; cache is hit on second call.

**Included subtasks**:
- [x] T006 Extend `#[ContentEntityType]` with `label`, `description` (WP02)
- [x] T007 Extend `EntityClassMetadata` (WP02)
- [x] T008 Update `EntityMetadataReader::forClass()` (WP02)
- [x] T009 Add `EntityMetadataReader::resolveFields()` (WP02)
- [x] T010 Tests for the above (WP02)

**Implementation sketch**:
1. Add `label`, `description` parameters to `ContentEntityType` constructor with sensible defaults.
2. Add `$label`, `$description`, `$fields` slots to `EntityClassMetadata`.
3. Extend `forClass()` to read `label`/`description` from the attribute (mirrors existing `resolveTypeId` pattern).
4. Implement `resolveFields()` walking the class hierarchy, mirroring `resolveKeys()`. Construct `FieldDefinition` instances using `FieldTypeInferrer`. Populate the cache.
5. Add tests using attribute-decorated test fixtures created on the fly under `packages/entity/tests/Fixtures/AttributeFirstEntities/`.

**Parallel opportunities**: T006-T009 are sequential within the same file scope; T010 is a single test layer that batches.

**Dependencies**: WP01.

**Risks**:
- Backed-enum reflection — `ReflectionProperty::getType()` returns a `ReflectionNamedType` whose name is the enum class. Test that we detect "is BackedEnum subclass" cleanly.
- Default value derivation: `Field(default: null)` for a non-nullable property should NOT set `required: false`. Sentinel-vs-null disambiguation.

**Estimated prompt size**: ~400 lines.
**Prompt**: [`tasks/WP02-metadata-reader-extensions.md`](./tasks/WP02-metadata-reader-extensions.md)

---

### WP03 — `EntityType::fromClass()`, Constructor Cleanup, TestEntityType Helper

**Goal**: The public API change. Add the static factory; remove the `fieldDefinitions:` constructor parameter; delete the metadata-match validator; ship the test helper.

**Priority**: P0. This is the API-break commit. After this WP, every downstream consumer must migrate.

**Independent test**: `EntityType::fromClass(SampleEntity::class)` returns an `EntityType` whose field map matches the class's `#[Field]` declarations. Cache assertions pass. NFR benchmarks pass.

**Included subtasks**:
- [x] T011 Add `EntityType::fromClass()` factory (WP03)
- [x] T012 Remove `fieldDefinitions:` from `EntityType` constructor (WP03)
- [x] T013 Delete `assertClassMetadataMatchesEntityType()` from `EntityTypeManager` (WP03)
- [x] T014 Add `TestEntityType::stub()` helper (WP03)
- [x] T015 Tests for `fromClass()` (WP03)
- [x] T016 Performance benchmark test (NFR-001/002) (WP03)

**Implementation sketch**:
1. Add `EntityType::fromClass(string $class, ...$overrides): self` — accepts `group`, `storageClass`, `revisionable`, etc. as named overrides; pulls everything else from attributes.
2. Internalize field-definition passing. Two clean options (pick during implementation): (a) keep a private constructor parameter `_fieldDefinitions`; (b) `EntityType` exposes a private `withFieldDefinitions()` clone method since it's `readonly`.
3. Remove `fieldDefinitions:` from the public constructor signature. PHPStan will surface every call site.
4. Delete `assertClassMetadataMatchesEntityType()` and remove its call from `registerEntityType()`.
5. Build `TestEntityType::stub()` under `packages/entity/tests/Helper/`.
6. Tests: happy path, inheritance, error paths (no `#[ContentEntityType]`, conflicting types, unknown explicit type id), cache hit assertions, NFR benchmark using a real fixture entity with 12 fields.

**Parallel opportunities**: T015, T016 are `[P]` test work after the API surface is in place.

**Dependencies**: WP02.

**Risks**:
- Breaking `EntityType` constructor signature breaks 45 call sites simultaneously. WP04-WP07 must follow promptly to restore green tests. PHPStan and phpunit will be red between WP03 and the migration WPs — expected.
- `TestEntityType::stub()` must accept raw `FieldDefinition` instances or a raw shape array. Keep the surface minimal.

**Estimated prompt size**: ~450 lines.
**Prompt**: [`tasks/WP03-entitytype-factory-and-cleanup.md`](./tasks/WP03-entitytype-factory-and-cleanup.md)

---

### WP04 — Migrate Production Entity Classes (Content Track)

**Goal**: Migrate the content-track entity classes (genealogy, node, note, taxonomy, user) and their ServiceProviders to attribute-first form.

**Priority**: P1. Restores green tests in content-track packages.

**Independent test**: phpunit for `packages/{genealogy,node,note,taxonomy,user}/` is green.

**Included subtasks**:
- [x] T017 Migrate genealogy entity classes (WP04)
- [x] T018 Migrate `packages/node/src/Node.php` (WP04)
- [x] T019 Migrate `packages/note/src/Note.php` (WP04)
- [x] T020 Migrate `packages/taxonomy/src/{Term,Vocabulary}.php` (WP04)
- [x] T021 Migrate `packages/user/src/User.php` (WP04)
- [x] T022 Update content-track ServiceProviders (WP04)

**Implementation sketch**:
1. For each entity class, add `#[ContentEntityType(id: …, label: …, description: …)]` (taking values from the corresponding ServiceProvider's current `EntityType` instantiation); add `#[Field]` to public typed properties matching the existing `fieldDefinitions:` map.
2. Where a property doesn't yet exist on the class but is in the ServiceProvider's field map, add the property with a typed declaration.
3. Drop the boilerplate constructor override unless it does something meaningful beyond delegation (it usually doesn't).
4. Update each ServiceProvider's `register()` to call `$this->entityType(EntityType::fromClass(MyEntity::class, group: …))` instead of inlining `fieldDefinitions:`.
5. Run package phpunit to verify.

**Parallel opportunities**: T017-T021 are file-disjoint and can run in parallel; T022 is the gluing step.

**Dependencies**: WP03.

**Risks**:
- Some entity classes may have field types that don't map cleanly (e.g., `entity_reference`). Use `#[Field(type: 'entity_reference', settings: [...])]` for those.
- `defaults/core.note.yaml` etc. may need synchronization — the comment in `NoteServiceProvider` says "keep in sync."

**Estimated prompt size**: ~400 lines.
**Prompt**: [`tasks/WP04-migrate-content-entities.md`](./tasks/WP04-migrate-content-entities.md)

---

### WP05 — Migrate Production Entity Classes (Identity / Feature Track) + MakeEntityTypeCommand

**Goal**: Migrate the identity-and-feature-track entity classes (oidc, engagement, groups, messaging, path) and their ServiceProviders. Update the `make:entity-type` CLI generator to emit attribute-first scaffolds.

**Priority**: P1. Restores green tests for these packages and aligns the codegen tool with the new pattern.

**Independent test**: phpunit for `packages/{oidc,engagement,groups,messaging,path,cli}/` is green; `vendor/bin/waaseyaa make:entity-type` (smoke test) generates attribute-decorated output.

**Included subtasks**:
- [x] T023 Migrate `packages/oidc/src/Entity/OidcClient.php` + provider (WP05)
- [x] T024 Migrate engagement entity + provider (WP05)
- [x] T025 Migrate groups entity + provider (WP05)
- [x] T026 Migrate messaging entity + provider (WP05)
- [x] T027 Migrate path entity + provider (WP05)
- [x] T028 Update `MakeEntityTypeCommand` to emit attribute-first scaffold (WP05)

**Implementation sketch**: Same pattern as WP04 per entity. The `MakeEntityTypeCommand` template change is its own WP-internal sub-step — find the current template, rewrite to emit `#[ContentEntityType]` + `#[Field]` annotations + `EntityType::fromClass()` registration line.

**Parallel opportunities**: T023-T027 are file-disjoint; T028 is mostly orthogonal (just template rewrites).

**Dependencies**: WP03.

**Risks**:
- `OidcClient` carries security-sensitive fields; double-check the type and storage match what's in `defaults/oidc/oidc_client.yaml`.
- `MakeEntityTypeCommand` may have its own test fixtures using the old shape; update those alongside the generator.

**Estimated prompt size**: ~400 lines.
**Prompt**: [`tasks/WP05-migrate-identity-entities-and-codegen.md`](./tasks/WP05-migrate-identity-entities-and-codegen.md)

---

### WP06 — Migrate Test Fixtures (Cluster A: entity, entity-storage, api, mcp, graphql)

**Goal**: Mechanical migration of test fixtures and inline `EntityType(fieldDefinitions: …)` calls in the highest-density packages.

**Priority**: P1. Restores green tests for these packages.

**Independent test**: phpunit for `packages/{entity,entity-storage,api,mcp,graphql}/` is green.

**Included subtasks**:
- [x] T029 Migrate `packages/entity/tests/Unit/` fixtures (WP06)
- [x] T030 Migrate `packages/entity-storage/tests/Unit/` fixtures (WP06)
- [x] T031 Migrate `packages/api/tests/` fixtures (WP06)
- [x] T032 Migrate `packages/mcp/tests/Unit/` fixtures (WP06)
- [x] T033 Migrate `packages/graphql/tests/Unit/` fixtures (WP06)
- [x] T034 Run package phpunit; verify green (WP06)

**Implementation sketch**: For each test file currently using `new EntityType(... fieldDefinitions: [...])`, decide:
- If the test exercises real entity behavior → create a small attribute-decorated test entity in `packages/<pkg>/tests/Fixtures/AttributeFirstEntities/<Name>.php` and use `EntityType::fromClass(...)`.
- If the test exercises raw `EntityType` shape only (e.g., schema-controller tests) → switch to `TestEntityType::stub('id', [FieldDefinition instances])`.

Keep changes line-for-line minimal — same field shapes, same test assertions.

**Parallel opportunities**: T029-T033 are package-disjoint and can run in parallel. T034 is the verification gate.

**Dependencies**: WP03.

**Risks**: Some test fixtures may rely on `fieldDefinitions:` accepting raw arrays (not `FieldDefinition` objects). The migration should normalize to `FieldDefinition` instances throughout.

**Estimated prompt size**: ~350 lines.
**Prompt**: [`tasks/WP06-migrate-fixtures-cluster-a.md`](./tasks/WP06-migrate-fixtures-cluster-a.md)

---

### WP07 — Migrate Test Fixtures (Cluster B: genealogy, ssr, testing, ai-vector, admin-surface, cli)

**Goal**: Same as WP06, for the remaining packages.

**Priority**: P1.

**Independent test**: phpunit for `packages/{genealogy,ssr,testing,ai-vector,admin-surface,cli}/` is green.

**Included subtasks**:
- [ ] T035 Migrate `packages/genealogy/tests/Unit/` fixtures (WP07)
- [ ] T036 Migrate `packages/ssr/tests/Unit/` fixtures (WP07)
- [ ] T037 Migrate `packages/testing/tests/Unit/` fixtures (WP07)
- [ ] T038 Migrate remaining test fixtures: ai-vector, admin-surface, cli (WP07)
- [ ] T039 Run package phpunit; verify green (WP07)

**Implementation sketch**: Same approach as WP06.

**Parallel opportunities**: T035-T038 are package-disjoint.

**Dependencies**: WP03.

**Risks**: `packages/testing/` is a test-helper package — its fixtures may be consumed by other packages' tests, so changes ripple. Audit `EntityFactory` usage carefully.

**Estimated prompt size**: ~300 lines.
**Prompt**: [`tasks/WP07-migrate-fixtures-cluster-b.md`](./tasks/WP07-migrate-fixtures-cluster-b.md)

---

### WP08 — Documentation, CHANGELOG, UPGRADING

**Goal**: Update authoritative docs to reflect the new attribute-first flow.

**Priority**: P2. Required for releasability but doesn't block test green.

**Independent test**: Documentation reads correctly; `docs/specs/entity-system.md` no longer shows `EntityType(fieldDefinitions: …)` examples.

**Included subtasks**:
- [ ] T040 Update `docs/specs/entity-system.md` (WP08)
- [ ] T041 Update `CHANGELOG.md` with breaking-change entry (WP08)
- [ ] T042 Update `UPGRADING.md` with migration recipe (WP08)
- [ ] T043 Update mission status meta + stub follow-on missions to reference M1 merging (WP08)

**Implementation sketch**:
1. Replace §EntityType Definition in entity-system.md with the new attribute-first example. Mark the §Public Surface section to list `EntityType::fromClass()` as the canonical content-entity registration entry point. Update any code samples elsewhere in the doc.
2. CHANGELOG entry: `[breaking] EntityType constructor no longer accepts fieldDefinitions:; use EntityType::fromClass() with #[Field]-decorated entity classes.`
3. UPGRADING recipe: a small worked example mirroring `quickstart.md`.
4. Update meta.json files of M2-M5 stub missions to set `status: ready` (from `stub`) once M1 is in.

**Parallel opportunities**: T041-T043 are file-disjoint.

**Dependencies**: WP04 + WP05 (both production migrations done).

**Risks**: docs/specs/entity-system.md is large (1438 lines) — be surgical, replace only what changes. Don't accidentally rewrite unrelated sections.

**Estimated prompt size**: ~300 lines.
**Prompt**: [`tasks/WP08-documentation-and-changelog.md`](./tasks/WP08-documentation-and-changelog.md)

---

### WP09 — Final Verification: PHPStan, Test Suite, Benchmarks, Success Criteria

**Goal**: Final gate. Everything green; M1 ready to merge.

**Priority**: P0.

**Independent test**: All checks below pass.

**Included subtasks**:
- [ ] T044 Refresh `phpstan-baseline.neon`; `vendor/bin/phpstan analyse` clean (WP09)
- [ ] T045 Run full `vendor/bin/phpunit`; suite green (WP09)
- [ ] T046 Verify success criteria SC-001 through SC-005 with grep + manual checks (WP09)
- [ ] T047 Verify NFR-001/NFR-002 benchmarks (WP09)

**Implementation sketch**:
1. Run `vendor/bin/phpstan analyse --memory-limit=2G`; capture any new errors. If genuine bugs, fix in WP09 (don't roll back to earlier WPs unless something fundamental is broken). If noise, regenerate baseline: `vendor/bin/phpstan analyse --generate-baseline`.
2. `vendor/bin/phpunit` — capture failures. Most should be already-green from WP06/WP07.
3. SC checks:
   - SC-002: `grep -rn 'fieldDefinitions:' packages/ | grep -v 'tests/Helper/TestEntityType' | wc -l` — expect 0 (or near-zero with explanations).
   - SC-003: `grep -rn 'assertClassMetadataMatchesEntityType' packages/ | wc -l` — expect 0.
4. NFR benchmarks: re-run the test from WP03.T016 on a clean checkout.

**Parallel opportunities**: T044, T045 can be parallel processes if your CI parallelizes. Sequential here for simplicity.

**Dependencies**: WP04, WP05, WP06, WP07, WP08.

**Risks**: PHPStan baseline drift — sometimes a test pass changes generic line numbers; baseline regeneration is normal.

**Estimated prompt size**: ~250 lines.
**Prompt**: [`tasks/WP09-final-verification.md`](./tasks/WP09-final-verification.md)

---

## MVP scope

**WP01 + WP02 + WP03** is the MVP — the framework's `#[Field]` attribute and `EntityType::fromClass()` factory exist, the public API is the new shape, and the metadata-match validator is gone. WP04-WP09 are the migration sweep that restores green tests and ships docs. Without WP04-WP09 the test suite is red and the docs are out of date, so the **shippable MVP** is M1 in its entirety; WP01-WP03 is the **architecturally complete MVP** that can be reviewed independently if needed.

---

## Parallelization summary

After WP03 lands, **WP04, WP05, WP06, WP07** can all execute in parallel (different package globs, no file overlap). WP08 (docs) waits on WP04+WP05; WP09 (verification) waits on everything.

Lane allocation (post-`finalize-tasks`):
- Lane A: WP01 → WP02 → WP03 (sequential, foundation)
- Lane B: WP04, WP08 (post-WP03)
- Lane C: WP05 (post-WP03)
- Lane D: WP06 (post-WP03)
- Lane E: WP07 (post-WP03)
- Convergence lane: WP09 (post all)

`spec-kitty agent mission finalize-tasks` will compute the actual lanes from these dependencies.
