# Implementation Plan: Attribute-First Entity Static Analysis

**Mission slug**: `attribute-first-entity-static-analysis-01KQ6XW7`
**Date**: 2026-04-27
**Spec**: [spec.md](spec.md)
**Target branch**: main

## Summary

Add a custom PHPStan rule (or small rule set) under `packages/entity/src/PhpStan/`
that lints `#[Field]` attribute usage at static-analysis time, surfacing the same
errors `FieldTypeInferrer::infer()` raises at runtime. The rule reuses the
existing public `FieldTypeInferrer::VALID_TYPE_IDS` constant and a newly-extracted
public compatibility-group surface so the rule and runtime stay in lock-step
without duplicating the table.

## Technical Context

**Language/Version**: PHP 8.4+
**Primary Dependencies (added)**: `phpstan/phpstan` (already a monorepo dev dependency, not yet in `packages/entity/composer.json` require-dev — will be added).
**Storage**: N/A.
**Testing**: PHPStan rule testing via `PHPStan\Testing\RuleTestCase` (ships with `phpstan/phpstan`); existing PHPUnit infra in `packages/entity/tests/` continues for any non-rule helper tests.
**Target Platform**: CI (Linux/Windows), local developer environments.
**Project Type**: Single PHP monorepo (`packages/entity/` is the unit of change).
**Performance Goals**: Rule must not increase `vendor/bin/phpstan analyse` wall-clock by more than 10% on the existing entity package baseline (NFR-001).
**Constraints**: PHPStan level 5+; no kernel/DI/runtime dependency (C-003); no runtime behavior change in `FieldTypeInferrer` (C-004); compatibility-group table must be the single source of truth shared with runtime (C-002).
**Scale/Scope**: Six detection rules (FR-001..FR-006), one rule registration, six fixture pairs, one doc update.

## Findings from spec-phase open questions

The spec deferred three questions to plan. Resolved:

1. **PHPStan extension config location.**
   The framework uses a single repo-root [phpstan.neon](../../phpstan.neon) with
   no per-package `extension.neon`. The new rule will be registered in that root
   file under `services:` (or via a new included `packages/entity/phpstan-rules.neon`
   that the root file `includes:`). Decision: ship a per-package neon at
   `packages/entity/phpstan-rules.neon` and `include:` it from the repo-root
   `phpstan.neon`. This keeps package-owned rules co-located with the package
   and lets downstream consumers of `packages/entity` opt in by adding the
   same `include:` line to their own `phpstan.neon`.

   This refines FR-008: "extension.neon" in the spec is realized as a
   package-local `phpstan-rules.neon` includable by consumers.

2. **Compatibility-group access.**
   `FieldTypeInferrer::COMPATIBILITY_GROUPS` and `SCALAR_MAP` are currently
   `private`. C-002 requires the rule to consume the same table. Decision:
   add **two** new public, side-effect-free static helpers on `FieldTypeInferrer`:

   - `public static function compatibilityGroups(): array` — returns the
     existing private constant verbatim.
   - `public static function inferFromPhpTypeName(?string $phpTypeName): ?string`
     — pure function the rule can call without constructing
     `\ReflectionProperty` objects, for the compat check.

   No private constant is made public directly; helpers preserve the
   inferrer's API surface and let the runtime evolve internals later. This
   change is the only `FieldTypeInferrer` modification; runtime behavior of
   `infer()` is unchanged (C-004 still satisfied — the helpers are additive).

3. **One rule vs. several.**
   Decision: **one** rule class `FieldAttributeRule` implementing
   `PHPStan\Rules\Rule` for `Node\Stmt\Property` (since `#[Field]` is a
   property-level attribute). Inside, six discrete check methods, one per FR,
   each returning a list of `RuleError` with a stable identifier suffix
   (`field.nonPublic`, `field.cannotInfer`, `field.unionType`,
   `field.unknownType`, `field.incompatibleType`, `field.notEntity`). Single
   class keeps registration simple and avoids re-walking the AST six times;
   stable identifiers let downstream consumers ignore individual checks via
   PHPStan's standard `ignoreErrors:` mechanism if needed.

## Charter Check

The framework's CLAUDE.md and `docs/specs/` were reviewed. No charter violations:

- Layered architecture: `packages/entity/src/PhpStan/` lives inside layer 1
  (Core Data); the rule has no dependencies on higher layers (C-003 reinforces).
- No forbidden Laravel/Drupal patterns introduced.
- Persistence layer untouched.
- Entity contract untouched (M1's `#[Field]` is the input; we only read it).

Re-check after Phase 1 design: still clean.

## Project Structure

### Documentation (this feature)

```
kitty-specs/attribute-first-entity-static-analysis-01KQ6XW7/
├── spec.md
├── plan.md              # this file
├── checklists/requirements.md
└── tasks/               # populated by /spec-kitty.tasks
```

No separate `research.md`, `data-model.md`, or `contracts/` documents are
needed: the runtime authority `FieldTypeInferrer.php` is the data model, and
the spec's FR table is the contract.

### Source Code (repository root)

```
packages/entity/
├── src/
│   ├── Attribute/
│   │   ├── Field.php                  # unchanged
│   │   └── FieldTypeInferrer.php      # +2 public helper methods (additive)
│   └── PhpStan/                       # NEW
│       └── FieldAttributeRule.php     # NEW — single Rule class
├── tests/
│   ├── PhpStan/                       # NEW
│   │   ├── FieldAttributeRuleTest.php # extends RuleTestCase
│   │   └── data/                      # fixture .php files (one per FR)
│   │       ├── nonPublicProperty.php
│   │       ├── cannotInferUntyped.php
│   │       ├── cannotInferUnion.php
│   │       ├── unknownTypeId.php
│   │       ├── incompatibleType.php
│   │       └── notEntityClass.php
│   └── Unit/Attribute/FieldTypeInferrerTest.php   # +covers new public helpers
├── composer.json                      # +require-dev phpstan/phpstan
└── phpstan-rules.neon                 # NEW — service registration

phpstan.neon                           # repo root, +1 line: includes packages/entity/phpstan-rules.neon
docs/specs/entity-system.md            # +section: "Static analysis of #[Field]"
```

**Structure Decision**: Single PHP package modification (`packages/entity/`)
plus one root config touch and one doc update. Layout mirrors the existing
package convention — code under `src/<Subsystem>/`, tests mirroring under
`tests/<Subsystem>/`.

## Phase 0: Research

Resolved inline above (see "Findings from spec-phase open questions"). No
external research artifacts required.

## Phase 1: Design

### Rule shape

```php
namespace Waaseyaa\Entity\PhpStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<Node\Stmt\Property> */
final class FieldAttributeRule implements Rule
{
    public function getNodeType(): string { return Node\Stmt\Property::class; }

    public function processNode(Node $node, Scope $scope): array
    {
        // 1. Find #[Field] on this property, else return [].
        // 2. Resolve declaring class FQCN from $scope.
        // 3. Run six checks (FR-001..FR-006), accumulate RuleErrorBuilder errors.
        // 4. Return.
    }
}
```

### Per-check contracts

| FR | Check | Error identifier | Message template |
|----|-------|------------------|------------------|
| FR-001 | property is not `public` | `field.nonPublic` | `Field attribute requires public property; got {visibility} on {class}::${property}` |
| FR-002 | no `type:` arg AND property has no PHP type declaration | `field.cannotInfer` | mirrors `FieldTypeInferrer::cannotInferException()` wording, branch "property has no type declaration" |
| FR-003 | no `type:` arg AND property type is union or intersection | `field.cannotInfer` (same id, different reason text) | mirrors "union types are not supported" / "intersection types are not supported" branch |
| FR-004 | `type:` is not in `FieldTypeInferrer::VALID_TYPE_IDS` | `field.unknownType` | mirrors `assertValidTypeId()` exception text, including the joined valid id list |
| FR-005 | explicit `type:` is incompatible with the property's PHP type per `FieldTypeInferrer::compatibilityGroups()` and `inferFromPhpTypeName()` | `field.incompatibleType` | mirrors `conflictException()` text |
| FR-006 | declaring class does not extend `ContentEntityBase` (transitive via PHPStan `ReflectionProvider`) | `field.notEntity` | `#[Field] used on {class}::${property} but {class} does not extend Waaseyaa\Entity\ContentEntityBase` |

Backed-enum + explicit `type: 'string'` rejection (the runtime's
`backedEnumExplicitStringRejection`) is **not** an FR for this mission. It
will fall out of FR-005 once `inferFromPhpTypeName()` returns `'enum'` for a
backed-enum and the existing compat groups reject `'enum' vs 'string'`.
Verify with a fixture; if it doesn't reproduce, file a follow-up — out of
scope here.

### `FieldTypeInferrer` API additions (only mutation outside `PhpStan/`)

```php
/**
 * @return list<list<string>>
 */
public static function compatibilityGroups(): array
{
    return self::COMPATIBILITY_GROUPS;
}

/**
 * Pure helper: maps a PHP type name (or null) to a field-type id, mirroring
 * the inference branch of infer() but without requiring a ReflectionProperty.
 * Used by static analysis. Returns null when the type is union/intersection
 * (caller passes null) or otherwise un-inferable.
 *
 * @param array<string,mixed> $settings  Out-parameter for backed-enum metadata.
 */
public static function inferFromPhpTypeName(?string $phpTypeName, array &$settings = []): ?string
{
    if ($phpTypeName === null) return null;
    return self::mapPhpTypeToFieldType($phpTypeName, $settings);
}
```

`mapPhpTypeToFieldType` stays private. The new helper is the public seam.
Existing `infer()` is untouched.

### Test approach

`packages/entity/tests/PhpStan/FieldAttributeRuleTest.php` extends
`PHPStan\Testing\RuleTestCase`. One `test*` method per FR:

```php
public function testNonPublicProperty(): void
{
    $this->analyse([__DIR__ . '/data/nonPublicProperty.php'], [
        ['Field attribute requires public property; got protected on App\Entity\Bad::$x', 12],
    ]);
}
```

Fixtures use a stable `App\Entity\…` namespace and minimal content. The
asserted error text is the **exact** runtime wording (FR-007) — verified by
running `FieldTypeInferrer::infer()` on an equivalent `ReflectionProperty`
and comparing strings in the test setup. (Optional: add a single integration
test that loads the fixture and asserts the runtime error string equals the
PHPStan error string, to enforce FR-007 mechanically.)

### NFR-001 verification approach

Tasks phase will produce a benchmark WP that runs:

```bash
vendor/bin/phpstan analyse --no-progress packages/entity/src 2>&1 | tail -5
```

three times before and three times after the rule is registered, captures
median wall-clock, and asserts after-median ≤ 1.10 × before-median.

### Documentation update

Add a short section to `docs/specs/entity-system.md` titled "Static analysis
of `#[Field]`" pointing at `packages/entity/src/PhpStan/FieldAttributeRule.php`
and documenting the `include: phpstan-rules.neon` opt-in for downstream
consumers. (Satisfies C-005.)

## Phase 2: Tasks (preview — actual breakdown by `/spec-kitty.tasks`)

Anticipated work-package shape:

- **WP01** — Extract public helpers on `FieldTypeInferrer`
  (`compatibilityGroups()`, `inferFromPhpTypeName()`); add unit tests for
  helpers in `FieldTypeInferrerTest`. No behavior change.
- **WP02** — Add `phpstan/phpstan` to `packages/entity/composer.json`
  require-dev; create empty `packages/entity/src/PhpStan/FieldAttributeRule.php`
  skeleton; create `packages/entity/phpstan-rules.neon`; wire `include:` in
  repo-root `phpstan.neon`; verify analysis still green.
- **WP03** — Implement FR-001 (non-public) + fixture + test.
- **WP04** — Implement FR-002 + FR-003 (cannot-infer cases) + fixtures + tests.
- **WP05** — Implement FR-004 (unknown type id) + fixture + test.
- **WP06** — Implement FR-005 (incompatible type override) + fixture + test.
- **WP07** — Implement FR-006 (class does not extend ContentEntityBase) +
  fixture + test.
- **WP08** — FR-007 string-equality cross-check test;
  FR-010 baseline run (analyse the existing entity-using packages, capture
  zero new errors); NFR-001 benchmark WP.
- **WP09** — Doc update in `docs/specs/entity-system.md` (C-005).

WP02 unblocks WP03..WP08; WP01 unblocks WP06. WP08 and WP09 close the
mission.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|--------------------------------------|
| Adding two public helpers to `FieldTypeInferrer` | C-002 requires a single source of truth shared between rule and runtime | Duplicating the compatibility table in the rule violates C-002 directly; making the private constants public exposes more surface area than needed |
| Per-package `phpstan-rules.neon` (vs. registering directly in repo-root `phpstan.neon`) | Lets downstream consumers of `packages/entity` (e.g., `course-journey`) opt in with one `include:` line | Inlining in repo-root `phpstan.neon` only helps the framework monorepo; downstream apps would have to re-declare every rule manually |
