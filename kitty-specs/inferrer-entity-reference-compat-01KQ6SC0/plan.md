# Implementation Plan: Inferrer entity_reference compat

**Branch**: `main` (target) | **Date**: 2026-04-28 | **Spec**: [spec.md](spec.md)
**Mission slug**: `inferrer-entity-reference-compat-01KQ6SC0`
**Mission ID**: `01KQ6SC0W3PQQ0BYBYRKBEKWTX`

## Summary

Extend `FieldTypeInferrer::isCompatible()` so PHP `int`/`?int`/`string`/`?string` properties may be explicitly overridden to `#[Field(type: 'entity_reference', ...)]`. Add the rule as a one-direction allowlist (not a symmetric compatibility group). Refactor the two M1 workaround sites (`Node.uid`, `Term.parent_id`) to use typed declarations. Close transitional gap #3 in `docs/specs/entity-system.md`.

## Technical Context

**Language/Version**: PHP 8.4+, `declare(strict_types=1)`
**Primary Dependencies**: PHP Reflection API; `Waaseyaa\Entity\Attribute\Field`, `Waaseyaa\Entity\Exception\EntityMetadataException`
**Storage**: N/A — pure metadata-resolver change; no DB migration; no JSON:API/GraphQL schema impact
**Testing**: PHPUnit 10.5 attribute-driven tests (`#[Test]`, `#[DataProvider]`); existing `FieldTypeInferrerTest` is the home for new cases
**Target Platform**: same as framework (CLI + cli-server SAPI for tests)
**Project Type**: PHP monorepo (single project; package layer = Layer 1 Core Data)
**Performance Goals**: N/A — single conditional branch in a static helper, called once per attribute resolution
**Constraints**: must not change `compatibilityGroups()` public seam contract; must preserve `target_entity_type_id` settings key; must not introduce new PHPStan level-5 findings
**Scale/Scope**: 6 files modified across 3 packages (entity, node, taxonomy) + 1 spec doc

## Charter Check

Doctrine context: paradigms `domain-driven-design`; directives `DIR-001`, `DIR-002`, `DIR-003`. No charter violations: this is a layer-1 change inside `packages/entity` with two layer-2 consumer refactors (`packages/node`, `packages/taxonomy`). Imports only flow downward. No new cross-package coupling.

## Phase 0 — Research

See [research.md](research.md). All decisions resolved:
- D1: asymmetric override rule, not a new symmetric compatibility group
- D2: preserve `target_entity_type_id` settings key (canonical name; brief's `target_type` was a typo)
- D3: continue rejecting `bool` / `float` / etc.

## Phase 1 — Design

### Data Model

See [data-model.md](data-model.md). No entity, attribute, or relationship changes; only PHP property type declarations on `Node.uid` and `Term.parent_id` are tightened from untyped to `?int`.

### Surface Change

`packages/entity/src/Attribute/FieldTypeInferrer.php`:
```php
private const ENTITY_REFERENCE_COMPATIBLE_INFERRED = ['integer', 'string'];

private static function isCompatible(string $inferred, string $explicit): bool
{
    if ($inferred === $explicit) {
        return true;
    }
    foreach (self::COMPATIBILITY_GROUPS as $group) {
        if (\in_array($inferred, $group, true) && \in_array($explicit, $group, true)) {
            return true;
        }
    }
    // NEW: asymmetric scalar → entity_reference rule.
    if ($explicit === 'entity_reference'
        && \in_array($inferred, self::ENTITY_REFERENCE_COMPATIBLE_INFERRED, true)) {
        return true;
    }
    return false;
}
```

`compatibilityGroups()` is **not** modified.

### Contracts

No new public methods. The behavioural contract change is documented in `spec.md` FR-001..FR-004 and exercised by tests.

### Quickstart (developer-facing)

Authoring an FK field after this mission:
```php
#[Field(type: 'entity_reference', settings: ['target_entity_type_id' => 'user'])]
public ?int $author_id = null;        // nullable FK
```
or for UUID-keyed targets:
```php
#[Field(type: 'entity_reference', settings: ['target_entity_type_id' => 'thing'])]
public ?string $thing_uuid = null;
```

## Project Structure

### Documentation (this feature)

```
kitty-specs/inferrer-entity-reference-compat-01KQ6SC0/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── spec.md              # Specification (Mission Spec)
├── meta.json            # Identity metadata
└── tasks/               # Created by /spec-kitty.tasks (Phase 2)
```

### Source Code (repository root)

```
packages/entity/
├── src/Attribute/FieldTypeInferrer.php          # ← modified (rule + constant)
└── tests/
    ├── Unit/Attribute/FieldTypeInferrerTest.php # ← extended (data cases + reject test)
    └── Fixtures/AttributeFirstEntities/
        └── InferrerTestFixtures.php              # ← extended (4 new properties)

packages/node/src/Node.php                        # ← refactor `uid`
packages/taxonomy/src/Term.php                    # ← refactor `parent_id`

docs/specs/entity-system.md                       # ← close gap #3 bullet
```

**Structure Decision**: existing PHP monorepo layout; no new directories.

## Phase 2 — Tasks (work-package outline)

To be expanded by `/spec-kitty.tasks` into per-WP files. Anticipated shape:

| WP | Title | Files | Depends |
|---|---|---|---|
| WP01 | Inferrer compatibility rule + tests | `FieldTypeInferrer.php`, `FieldTypeInferrerTest.php`, `InferrerTestFixtures.php` | — |
| WP02 | Refactor M1 workaround sites | `Node.php`, `Term.php` | WP01 |
| WP03 | Close gap #3 in entity-system spec | `docs/specs/entity-system.md` | WP02 |

WP01 is TDD: write the failing data-provider cases first, then add the compatibility rule, then green.

## Verification

End-to-end at `/spec-kitty.accept`:
1. `./vendor/bin/phpunit packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php` — green, including new cases
2. `./vendor/bin/phpunit packages/node/tests packages/taxonomy/tests` — green
3. `./vendor/bin/phpunit` — full suite green
4. `composer phpstan` — no new findings
5. `composer cs-check` — clean
6. `bash tools/drift-detector.sh` — confirms `entity-system.md` reflects closed gap

## Complexity Tracking

No charter violations. No complexity beyond what the requirement demands.

## Branch Contract

- Current branch at workflow start: `main`
- Planning/base branch: `main`
- Final merge target: `main`
- `branch_matches_target`: `true`

WP implementation worktrees (e.g., `.worktrees/inferrer-entity-reference-compat-01KQ6SC0-lane-a/`) will be created by `spec-kitty next` after `/spec-kitty.tasks`.
