# Spec: Attribute-First Entity Static Analysis

**Mission slug**: `attribute-first-entity-static-analysis-01KQ6XW7`
**Mission ID**: `01KQ6XW7Y3QD0JJ7JTP9JCSDPM`
**Mission type**: software-dev
**Target branch**: main
**Created**: 2026-04-27

## Why

M1 (`attribute-first-entity-definition-01KQ6DXE`, merged as `ce123bfe`) introduced
the `#[Field]` PHP attribute as the primary way to declare entity fields.
`FieldTypeInferrer::infer()` performs strict validation at runtime — a typo such
as `#[Field(type: 'integerr')]` is only surfaced when `EntityType::fromClass()`
runs at kernel boot or registration time. That is too late: developers discover
mistakes through runtime exceptions instead of CI signal, and consumers of the
framework cannot block bad attribute usage from landing on `main`.

This mission moves the same validation rules into PHPStan so that misuses of
`#[Field]` are caught during static analysis — in the developer's editor and in
CI — without changing any runtime behavior.

## Scope

### In scope

A custom PHPStan rule (or small rule set) shipped from `packages/entity` that
lints `#[Field]` usage and emits errors that mirror `FieldTypeInferrer::infer()`
exactly. Test fixtures and PHPStan-level test coverage for every rule.
Registration of the rule in the entity package's PHPStan extension config so
that any project depending on `packages/entity` and running PHPStan picks it up
automatically.

### Out of scope

- Changing runtime inference behavior in `FieldTypeInferrer`.
- Replacing `EntityType::fromClass()` validation; the runtime checks remain a
  defense-in-depth layer.
- Rules for other entity attributes (`#[Bundle]`, vector-indexed fields, etc.).
  Those have or will have their own missions.
- Auto-fix / code-mod support. PHPStan errors are surfaced; developers fix by
  hand.

## Users / Actors

- **Framework contributors** writing entity classes inside the Waaseyaa
  monorepo; benefit is fastest, since PHPStan runs on every commit.
- **Downstream Waaseyaa app developers** (e.g., this `course-journey` repo) who
  depend on `packages/entity` and run PHPStan in their own CI.
- **CI pipelines** that gate merges on PHPStan passing; bad `#[Field]` usage
  becomes a hard CI failure rather than a runtime surprise.

## User Scenarios

1. A contributor types `#[Field(type: 'integerr')]` (typo) on a property in a
   new entity. Their editor's PHPStan integration immediately underlines the
   attribute and reports the same error wording the kernel would have raised at
   boot.
2. A contributor adds `#[Field]` to an untyped property without an explicit
   `type:` argument. PHPStan reports a "cannot infer field type" error on the
   property declaration line.
3. A contributor declares `#[Field(type: 'integer')] public string $count;` —
   PHPStan reports the type override is incompatible with the property's
   declared PHP type, citing the same compatibility-group rule
   `FieldTypeInferrer` enforces at runtime.
4. A contributor places `#[Field]` on a non-public property, or on a class that
   does not extend `ContentEntityBase`. PHPStan reports the misuse with class
   and property location.
5. A pull request containing any of the above arrives in CI. PHPStan fails the
   build before any test runs and before the kernel boots.

## Functional Requirements

| ID | Requirement | Status |
|----|-------------|--------|
| FR-001 | The PHPStan rule MUST detect `#[Field]` on a non-public property and emit an error of the form `"Field attribute requires public property; got {visibility} on {class}::${property}"`. | Required |
| FR-002 | The PHPStan rule MUST detect `#[Field]` on a property without a declared PHP type and without an explicit `type:` argument, and emit an error matching `FieldTypeInferrer::infer()`'s "cannot infer field type" message. | Required |
| FR-003 | The PHPStan rule MUST detect `#[Field]` on a property whose declared type is a union or intersection type, when no explicit `type:` argument is provided, and emit the same "cannot infer field type" error. | Required |
| FR-004 | The PHPStan rule MUST detect `#[Field(type: '<unknown>')]` where `<unknown>` is not one of the 16 valid field type ids, and emit an error listing the valid ids. | Required |
| FR-005 | The PHPStan rule MUST detect an explicit `type:` argument that is incompatible with the property's declared PHP type per `FieldTypeInferrer`'s compatibility groups (e.g., `type: 'integer'` on `public string $x`), and emit an error referencing the conflict. | Required |
| FR-006 | The PHPStan rule MUST detect `#[Field]` on a property of a class that does not extend `ContentEntityBase` (directly or transitively) and emit an error. | Required |
| FR-007 | Error messages produced by the rule MUST match the wording of the corresponding runtime errors from `FieldTypeInferrer::infer()` so that the failure mode is identical regardless of whether it surfaces in PHPStan or at boot. | Required |
| FR-008 | The rule MUST be registered in the entity package's PHPStan extension configuration (`extension.neon` or equivalent already used by the package), so that any consumer running PHPStan with the package's extension enabled picks it up with no further configuration. | Required |
| FR-009 | The rule MUST ship with PHPStan-level tests under `packages/entity/tests/PhpStan/` (or the package's existing PHPStan test convention) covering one passing fixture and one failing fixture per FR-001..FR-006, asserting the exact error message and the exact line number. | Required |
| FR-010 | The rule MUST NOT report errors on entity classes that pass `FieldTypeInferrer::infer()` at runtime; i.e., zero false positives on the existing entity classes in the monorepo at the time of merge. | Required |

## Non-Functional Requirements

| ID | Requirement | Status |
|----|-------------|--------|
| NFR-001 | Running PHPStan on the entity package after the rule is added MUST not increase analysis wall-clock time by more than 10% on the existing baseline (measured by running `vendor/bin/phpstan analyse` before and after on the same machine). | Required |
| NFR-002 | The rule MUST work at PHPStan level 5 or higher; downstream projects using lower levels still pick up the rule via the package's extension. | Required |
| NFR-003 | The rule SHOULD run on PHP 8.4+ and use only PHP 8.4-compatible reflection / AST APIs already permitted by the framework. | Required |

## Constraints

| ID | Constraint | Status |
|----|-----------|--------|
| C-001 | Implementation lives under `packages/entity/src/PhpStan/` (or the existing PHPStan subdir convention used in the package; the implementation MUST first audit existing `PhpStan/` directories across framework packages and follow whichever convention is already in use). | Required |
| C-002 | The rule MUST mirror `FieldTypeInferrer::infer()`'s checks exactly. The 16 valid type ids and the compatibility-group table are the authoritative source; the rule re-uses (does not re-encode) that table. If the runtime list changes, the rule MUST stay in lock-step (no drift). | Required |
| C-003 | The rule MUST NOT depend on the kernel booting, on Symfony DI, or on any framework runtime service. PHPStan must be able to run it on raw source files. | Required |
| C-004 | No changes to `FieldTypeInferrer` runtime behavior in this mission. The runtime check remains the source of truth and a defense-in-depth backstop. | Required |
| C-005 | Documentation update: the entity package's existing developer-facing docs (e.g., `docs/specs/entity-system.md` or the package README) MUST mention that `#[Field]` is statically analyzed and how to opt in if the consumer's PHPStan config does not auto-load the extension. | Required |

## Assumptions

- `packages/entity` already declares PHPStan as a dev dependency (or will accept
  it being added) and already exposes a `phpstan` extension config consumers
  can include. The plan phase will verify the exact file name and adjust
  C-001/FR-008 accordingly.
- The compatibility-group table inside `FieldTypeInferrer` is exposed (or can be
  exposed without API churn) in a form the PHPStan rule can consume directly.
  If it is currently `private`, the plan phase will decide between extracting
  it to a dedicated class/constant or making the rule call a public helper on
  the inferrer.
- The 16 valid type ids referenced in FR-004 match the current
  `FieldTypeInferrer` constant; the rule must not hard-code a parallel list.

## Success Criteria

- A new entity class committed to the monorepo with `#[Field(type: 'integerr')]`
  causes `vendor/bin/phpstan analyse` to fail with the same error message that
  `FieldTypeInferrer::infer()` would raise at boot, without the kernel ever
  booting.
- All 6 detection cases from FR-001..FR-006 have a corresponding fixture in
  `packages/entity/tests/PhpStan/` and an assertion of the exact error text and
  line.
- Running `vendor/bin/phpstan analyse` against the existing entity-using
  packages in the monorepo before and after the change produces zero new
  errors (FR-010); CI on `main` stays green at merge.
- Wall-clock cost of PHPStan on `packages/entity` increases by no more than
  10% (NFR-001), measured by the plan phase's chosen benchmark command.

## Key Entities (informational)

- `FieldTypeInferrer` — runtime authority on `#[Field]` type inference. The
  PHPStan rule mirrors but does not modify it.
- `ContentEntityBase` — base class entities must extend; subject of FR-006.
- The PHPStan rule(s) themselves — new code introduced by this mission.

## Dependencies

- M1 `attribute-first-entity-definition-01KQ6DXE` (merged at `ce123bfe`) is a
  hard prerequisite; without it there is no `#[Field]` to lint.

## Open Questions

None at spec stage. The plan phase will resolve:

- Exact existing PHPStan extension file path inside `packages/entity`.
- Whether the compatibility-group table needs to be lifted out of
  `FieldTypeInferrer` or can be reached through an existing public helper.
- Whether one rule with multiple error codes or several small rules is the
  cleaner shape for FR-001..FR-006.
