# Tasks — Attribute-First Entity Static Analysis

**Mission**: `attribute-first-entity-static-analysis-01KQ6XW7`
**Mission ID**: `01KQ6XW7Y3QD0JJ7JTP9JCSDPM`
**Branch contract**: `main` → `main` (matches target).
**Work package count**: 9 — average ≈ 3 subtasks/WP.

---

## Subtask Index

| ID | Description | WP | Parallel |
|----|-------------|----|----------|
| T001 | Add `FieldTypeInferrer::compatibilityGroups()` public helper (returns existing private constant verbatim) | WP01 | [D] |
| T002 | Add `FieldTypeInferrer::inferFromPhpTypeName(?string, array&): ?string` public helper | WP01 | [D] |
| T003 | Extend `FieldTypeInferrerTest` with cases covering both new helpers | WP01 | [D] |
| T004 | Add `phpstan/phpstan` to `packages/entity/composer.json` require-dev | WP02 | [D] |
| T005 | Create `packages/entity/src/PhpStan/FieldAttributeRule.php` skeleton implementing `Rule` for `Node\Stmt\Property` (returns `[]` for now) | WP02 | [D] |
| T006 | Create `packages/entity/phpstan-rules.neon` registering the rule under `services:` | WP02 | [D] |
| T007 | Add `- packages/entity/phpstan-rules.neon` to repo-root `phpstan.neon` `includes:` | WP02 | [D] |
| T008 | Verify `vendor/bin/phpstan analyse --no-progress` is still green; no new errors | WP02 | [D] |
| T009 | Implement FR-001: detect `#[Field]` on non-public property; emit `field.nonPublic` error | WP03 | [D] |
| T010 | Add fixture `tests/PhpStan/data/nonPublicProperty.php` and rule test asserting exact runtime-equivalent message + line | WP03 | [D] |
| T011 | Implement FR-002 (untyped property without `type:`) — `field.cannotInfer` (no-declaration branch) | WP04 | [D] |
| T012 | Implement FR-003 (union or intersection without `type:`) — `field.cannotInfer` (union/intersection branch) | WP04 | [D] |
| T013 | Add fixtures `cannotInferUntyped.php` and `cannotInferUnion.php`; rule tests assert exact messages from `FieldTypeInferrer::cannotInferException()` and line numbers | WP04 | [D] |
| T014 | Implement FR-004: detect `#[Field(type: '<unknown>')]` not in `VALID_TYPE_IDS`; emit `field.unknownType` with joined valid-id list | WP05 | [D] |
| T015 | Add fixture `unknownTypeId.php` and rule test asserting exact wording from `assertValidTypeId()` | WP05 | [D] |
| T016 | Implement FR-005: detect explicit `type:` incompatible with property's PHP type via `compatibilityGroups()` + `inferFromPhpTypeName()`; emit `field.incompatibleType` matching `conflictException()` text | WP06 | [D] |
| T017 | Add fixture `incompatibleType.php` (e.g., `#[Field(type:'integer')] public string $x`) and rule test | WP06 | [D] |
| T018 | Implement FR-006: detect declaring class does not extend `ContentEntityBase` (use PHPStan `ReflectionProvider` for transitive check); emit `field.notEntity` | WP07 | [D] |
| T019 | Add fixture `notEntityClass.php` and rule test | WP07 | [D] |
| T020 | FR-007 cross-check: integration test that constructs `\ReflectionProperty` for each fixture, calls `FieldTypeInferrer::infer()`, and asserts the runtime exception message string-equals the PHPStan rule error message (where applicable to FR-002..FR-005) | WP08 | [D] |
| T021 | FR-010 baseline: run `vendor/bin/phpstan analyse` against existing entity-using packages; record zero new errors in `tasks/notes/baseline.md` (under mission dir) | WP08 | [D] |
| T022 | NFR-001 benchmark: capture median wall-clock of `vendor/bin/phpstan analyse packages/entity/src` before vs after; assert ≤ 1.10× | WP08 | [D] |
| T023 | C-005 doc update: add "Static analysis of `#[Field]`" section to `docs/specs/entity-system.md` | WP09 | [D] |
| T024 | Update `CHANGELOG.md` (and entity-package README if it advertises analysis) with the new rule | WP09 | [D] |

---

## Work Package Map

| WP | Title | Dependencies | Owned files |
|----|-------|--------------|-------------|
| WP01 | `FieldTypeInferrer` public helpers | — | `packages/entity/src/Attribute/FieldTypeInferrer.php`, `packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php` |
| WP02 | PHPStan wiring | WP01 | `packages/entity/composer.json`, `packages/entity/src/PhpStan/FieldAttributeRule.php`, `packages/entity/phpstan-rules.neon`, `phpstan.neon` |
| WP03 | FR-001 non-public | WP02 | `packages/entity/src/PhpStan/FieldAttributeRule.php`, `packages/entity/tests/PhpStan/FieldAttributeRuleTest.php`, `packages/entity/tests/PhpStan/data/nonPublicProperty.php` |
| WP04 | FR-002 + FR-003 cannot-infer | WP02 | (rule, test, fixtures `cannotInferUntyped.php`, `cannotInferUnion.php`) |
| WP05 | FR-004 unknown type id | WP02 | (rule, test, fixture `unknownTypeId.php`) |
| WP06 | FR-005 incompatible type | WP01, WP02 | (rule, test, fixture `incompatibleType.php`) |
| WP07 | FR-006 non-entity class | WP02 | (rule, test, fixture `notEntityClass.php`) |
| WP08 | FR-007 + FR-010 + NFR-001 verification | WP03..WP07 | `packages/entity/tests/PhpStan/FieldAttributeRuleTest.php` (cross-check), mission notes |
| WP09 | Documentation | WP08 | `docs/specs/entity-system.md`, `CHANGELOG.md` |

WPs 03..07 share `FieldAttributeRule.php` and `FieldAttributeRuleTest.php`; finalize-tasks will sequence them so they don't fight over the file. They could be collapsed into a single WP if the lane allocator complains — see C-002 in plan.md for rationale on keeping them separate (one rule branch per WP keeps reviewer cognitive load low).

---

## Requirement Mapping

| Requirement | WP(s) |
|-------------|-------|
| FR-001 | WP03 |
| FR-002 | WP04 |
| FR-003 | WP04 |
| FR-004 | WP05 |
| FR-005 | WP06 |
| FR-006 | WP07 |
| FR-007 | WP08 (cross-check); enforced through-out by mirroring runtime wording |
| FR-008 | WP02 |
| FR-009 | WP03..WP07 (per-FR fixtures) |
| FR-010 | WP08 |
| NFR-001 | WP08 |
| NFR-002 | WP02 (level 5 wiring) |
| NFR-003 | WP02 |
| C-001 | WP02 |
| C-002 | WP01, WP06 |
| C-003 | WP02 |
| C-004 | (asserted by absence of `infer()` mutation; checked at review) |
| C-005 | WP09 |

---

## Work Package Details

### WP01 — FieldTypeInferrer public helpers

**Goal**: Expose `compatibilityGroups()` and `inferFromPhpTypeName()` on `FieldTypeInferrer` so the PHPStan rule reuses the runtime's tables (C-002). Additive only; no `infer()` mutation (C-004).

**Priority**: P0 (foundation).

**Independent test**: Existing `FieldTypeInferrerTest` still green; new tests for the two helpers pass.

**Included subtasks**:
- [ ] T001 Add `compatibilityGroups()` (WP01)
- [ ] T002 Add `inferFromPhpTypeName()` (WP01)
- [ ] T003 Unit tests for both helpers (WP01)

**Dependencies**: None.

**Prompt**: [`tasks/WP01-fieldtypeinferrer-public-helpers.md`](./tasks/WP01-fieldtypeinferrer-public-helpers.md)

---

### WP02 — PHPStan rule wiring (skeleton)

**Goal**: Add empty-but-registered `FieldAttributeRule`, package-local `phpstan-rules.neon`, and `include:` from repo-root `phpstan.neon`. No detection logic.

**Priority**: P0 (gate for WP03..WP07).

**Independent test**: `vendor/bin/phpstan analyse` exits 0 with no new errors; rule class is reachable (proven by a temporary fake error during T008).

**Included subtasks**:
- [ ] T004 Add `phpstan/phpstan` to entity package require-dev (WP02)
- [ ] T005 Create `FieldAttributeRule` skeleton (WP02)
- [ ] T006 Create `packages/entity/phpstan-rules.neon` (WP02)
- [ ] T007 Wire `includes:` in repo-root `phpstan.neon` (WP02)
- [ ] T008 Verify analysis remains green (WP02)

**Dependencies**: WP01.

**Prompt**: [`tasks/WP02-phpstan-wiring.md`](./tasks/WP02-phpstan-wiring.md)

---

### WP03 — FR-001 detect non-public property

**Goal**: First detection rule branch — `field.nonPublic` identifier.

**Priority**: P1.

**Independent test**: `nonPublicProperty.php` fixture produces exactly one error matching the FR-001 wording.

**Included subtasks**:
- [ ] T009 Detection logic for non-public property (WP03)
- [ ] T010 Fixture + rule test (WP03)

**Dependencies**: WP02.

**Prompt**: [`tasks/WP03-fr001-non-public.md`](./tasks/WP03-fr001-non-public.md)

---

### WP04 — FR-002 + FR-003 cannot-infer cases

**Goal**: Detect untyped/union/intersection properties without explicit `type:`. Wording mirrors `FieldTypeInferrer::cannotInferException()`.

**Priority**: P1.

**Independent test**: Two fixtures (`cannotInferUntyped.php`, `cannotInferUnion.php`) each produce one error matching runtime wording.

**Included subtasks**:
- [ ] T011 FR-002 detection (untyped) (WP04)
- [ ] T012 FR-003 detection (union/intersection) (WP04)
- [ ] T013 Fixtures + rule tests (WP04)

**Dependencies**: WP02.

**Prompt**: [`tasks/WP04-fr002-fr003-cannot-infer.md`](./tasks/WP04-fr002-fr003-cannot-infer.md)

---

### WP05 — FR-004 unknown type id

**Goal**: Detect `#[Field(type: '<unknown>')]`. Wording mirrors `FieldTypeInferrer::assertValidTypeId()`.

**Priority**: P1.

**Independent test**: `unknownTypeId.php` fixture produces one error including the joined valid-id list.

**Included subtasks**:
- [ ] T014 Detection logic (WP05)
- [ ] T015 Fixture + rule test (WP05)

**Dependencies**: WP02.

**Prompt**: [`tasks/WP05-fr004-unknown-type-id.md`](./tasks/WP05-fr004-unknown-type-id.md)

---

### WP06 — FR-005 incompatible explicit type

**Goal**: Detect explicit `type:` incompatible with the declared PHP type via `compatibilityGroups()` + `inferFromPhpTypeName()`. Wording mirrors `FieldTypeInferrer::conflictException()`.

**Priority**: P1.

**Independent test**: `incompatibleType.php` flagged; `compatibleOverride.php` (same group) is not.

**Included subtasks**:
- [ ] T016 Detection logic (WP06)
- [ ] T017 Fixtures + rule tests (WP06)

**Dependencies**: WP01, WP02.

**Prompt**: [`tasks/WP06-fr005-incompatible-type.md`](./tasks/WP06-fr005-incompatible-type.md)

---

### WP07 — FR-006 class does not extend ContentEntityBase

**Goal**: Detect `#[Field]` on properties whose declaring class is not a transitive subclass of `ContentEntityBase`. Cascade-suppress other rules when this fires.

**Priority**: P1.

**Independent test**: `notEntityClass.php` produces one `field.notEntity` error and no others.

**Included subtasks**:
- [ ] T018 Detection logic + cascade suppression (WP07)
- [ ] T019 Fixture + rule test (WP07)

**Dependencies**: WP02.

**Prompt**: [`tasks/WP07-fr006-non-entity-class.md`](./tasks/WP07-fr006-non-entity-class.md)

---

### WP08 — FR-007 cross-check + FR-010 baseline + NFR-001 benchmark

**Goal**: Mechanically enforce string-equality with runtime errors (FR-007), prove no new errors on existing entity-using packages (FR-010), prove ≤ 10% wall-clock regression (NFR-001).

**Priority**: P0 (mission acceptance gate).

**Independent test**: Cross-check test green; baseline note records identical error counts; benchmark note records ≤ 1.10× median.

**Included subtasks**:
- [ ] T020 Runtime/static cross-check test (WP08)
- [ ] T021 FR-010 baseline note (WP08)
- [ ] T022 NFR-001 benchmark note (WP08)

**Dependencies**: WP03, WP04, WP05, WP06, WP07.

**Prompt**: [`tasks/WP08-cross-check-and-benchmark.md`](./tasks/WP08-cross-check-and-benchmark.md)

---

### WP09 — Documentation

**Goal**: Satisfy C-005. Add a "Static analysis of `#[Field]`" section to `docs/specs/entity-system.md` and a CHANGELOG entry.

**Priority**: P2.

**Independent test**: docs and changelog updated; no code changes.

**Included subtasks**:
- [ ] T023 Update `docs/specs/entity-system.md` (WP09)
- [ ] T024 Update `CHANGELOG.md` (WP09)

**Dependencies**: WP08.

**Prompt**: [`tasks/WP09-documentation.md`](./tasks/WP09-documentation.md)
