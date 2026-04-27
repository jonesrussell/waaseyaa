# Tasks — Attribute-First Entity Static Analysis

**Mission**: `attribute-first-entity-static-analysis-01KQ6XW7`
**Mission ID**: `01KQ6XW7Y3QD0JJ7JTP9JCSDPM`
**Branch contract**: `main` → `main` (matches target).
**Work package count**: 4.

WPs were collapsed from the plan's 9-WP sketch to 4 because the lane allocator
requires disjoint file ownership: WPs that share `FieldAttributeRule.php`
must merge into one. The sequencing rationale ("one rule branch per WP") is
preserved as 6 named subtasks inside WP02.

---

## Subtask Index

| ID | Description | WP | Parallel |
|----|-------------|----|----------|
| T001 | Add `FieldTypeInferrer::compatibilityGroups()` public helper | WP01 | [D] | [D] |
| T002 | Add `FieldTypeInferrer::inferFromPhpTypeName(?string, array&): ?string` public helper | WP01 | [D] |
| T003 | Extend `FieldTypeInferrerTest` with cases for both new helpers | WP01 | [D] |
| T004 | Add `phpstan/phpstan` to `packages/entity/composer.json` require-dev | WP02 | [D] |
| T005 | Create `FieldAttributeRule` skeleton at `packages/entity/src/PhpStan/FieldAttributeRule.php` | WP02 | [D] |
| T006 | Create `packages/entity/phpstan-rules.neon` registering the rule | WP02 | [D] |
| T007 | Append include to repo-root `phpstan.neon` | WP02 | [D] |
| T008 | Verify analysis still green; rule reachable | WP02 | [D] |
| T009 | FR-001 detection: non-public property → `field.nonPublic` | WP02 | [D] |
| T010 | FR-006 detection: non-`ContentEntityBase` declaring class → `field.notEntity`, cascade-suppress others | WP02 | [D] |
| T011 | FR-002 detection: untyped property without `type:` → `field.cannotInfer` | WP02 | [D] |
| T012 | FR-003 detection: union/intersection without `type:` → `field.cannotInfer` | WP02 | [D] |
| T013 | FR-004 detection: unknown type id → `field.unknownType` | WP02 | [D] |
| T014 | FR-005 detection: incompatible explicit type → `field.incompatibleType` (uses WP01 helpers) | WP02 | [D] |
| T015 | Create `FieldAttributeRuleTest` extending `RuleTestCase`; one test method per FR + a no-false-positive `compatibleOverride` guard | WP02 | [D] |
| T016 | Fixtures `nonPublicProperty.php`, `cannotInferUntyped.php`, `cannotInferUnion.php` | WP02 | [D] |
| T017 | Fixtures `unknownTypeId.php`, `incompatibleType.php`, `compatibleOverride.php` | WP02 | [D] |
| T018 | Fixture `notEntityClass.php` | WP02 | [D] |
| T019 | FR-007: in each test, build expected message by invoking `FieldTypeInferrer` runtime exception (where applicable to FR-002..FR-005) and assert string equality | WP02 | [D] |
| T020 | FR-010 baseline: capture monorepo PHPStan error counts before/after WP02; record in `notes/baseline.md` | WP03 | [D] |
| T021 | NFR-001 benchmark: capture median wall-clock 3× before / 3× after; assert ≤ 1.10×; record in `notes/benchmark.md` | WP03 | [D] |
| T022 | C-005 doc update: "Static analysis of `#[Field]`" section in `docs/specs/entity-system.md` | WP04 | [D] |
| T023 | CHANGELOG.md entry | WP04 | [D] |

---

## Work Package Map

| WP | Title | Dependencies | Owned files |
|----|-------|--------------|-------------|
| WP01 | `FieldTypeInferrer` public helpers | — | `packages/entity/src/Attribute/FieldTypeInferrer.php`, `packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php` |
| WP02 | `FieldAttributeRule` (all 6 branches + wiring) | WP01 | rule + neon + composer + repo-root phpstan.neon + test class + 7 fixtures |
| WP03 | FR-010 baseline + NFR-001 benchmark | WP02 | mission notes only |
| WP04 | Documentation (C-005) | WP03 | `docs/specs/entity-system.md`, `CHANGELOG.md` |

---

## Requirement Mapping

| Requirement | WP(s) |
|-------------|-------|
| FR-001 | WP02 |
| FR-002 | WP02 |
| FR-003 | WP02 |
| FR-004 | WP02 |
| FR-005 | WP01 (helpers), WP02 (detection) |
| FR-006 | WP02 |
| FR-007 | WP02 (in-test cross-check) |
| FR-008 | WP02 (wiring), WP04 (docs) |
| FR-009 | WP02 (per-FR fixtures) |
| FR-010 | WP03 |
| NFR-001 | WP03 |
| NFR-002 | WP02 |
| NFR-003 | WP02 |
| C-001 | WP02 |
| C-002 | WP01 |
| C-003 | WP02 |
| C-004 | enforced by absence of `infer()` mutation; checked at review |
| C-005 | WP04 |

---

## Work Package Details

### WP01 — FieldTypeInferrer public helpers

**Goal**: Expose `compatibilityGroups()` and `inferFromPhpTypeName()` on `FieldTypeInferrer` so the PHPStan rule reuses runtime tables (C-002). Additive only; no `infer()` mutation (C-004).

**Priority**: P0 (foundation).

**Independent test**: Existing `FieldTypeInferrerTest` still green; new tests for the two helpers pass.

**Included subtasks**: T001, T002, T003.

**Dependencies**: None.

**Prompt**: [`tasks/WP01-fieldtypeinferrer-public-helpers.md`](./tasks/WP01-fieldtypeinferrer-public-helpers.md)

---

### WP02 — FieldAttributeRule (all 6 detection branches + wiring)

**Goal**: Land the complete PHPStan rule with all six detection branches, registration in repo-root `phpstan.neon`, and full `RuleTestCase` suite with one fixture per FR.

**Priority**: P0 (mission core).

**Independent test**: `vendor/bin/phpunit packages/entity/tests/PhpStan` green; `vendor/bin/phpstan analyse` green with no new errors on existing entity-using packages.

**Included subtasks**: T004..T019 (16 subtasks).

**Dependencies**: WP01.

**Prompt**: [`tasks/WP02-phpstan-rule.md`](./tasks/WP02-phpstan-rule.md)

---

### WP03 — FR-010 baseline + NFR-001 benchmark

**Goal**: Mechanically verify FR-010 (zero new errors on existing entity-using packages) and NFR-001 (≤ 10% wall-clock regression). FR-007 is verified in-test as part of WP02.

**Priority**: P0 (mission acceptance gate).

**Independent test**: `notes/baseline.md` and `notes/benchmark.md` exist with the required data and assertions met.

**Included subtasks**: T020, T021.

**Dependencies**: WP02.

**Prompt**: [`tasks/WP03-cross-check-and-benchmark.md`](./tasks/WP03-cross-check-and-benchmark.md)

---

### WP04 — Documentation

**Goal**: Satisfy C-005 — announce the static analysis surface in `docs/specs/entity-system.md` and CHANGELOG.

**Priority**: P2.

**Independent test**: docs and changelog updated; no code changes.

**Included subtasks**: T022, T023.

**Dependencies**: WP03.

**Prompt**: [`tasks/WP04-documentation.md`](./tasks/WP04-documentation.md)
