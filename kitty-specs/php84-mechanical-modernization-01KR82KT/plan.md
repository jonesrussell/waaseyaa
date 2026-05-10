# Implementation Plan: PHP 8.4 Mechanical Modernization

**Branch**: `main` (planning base) → `main` (merge target) | **Date**: 2026-05-10 | **Spec**: [spec.md](./spec.md)
**Mission**: `php84-mechanical-modernization-01KR82KT`

## Summary

Apply ~15 behavior-preserving PHP 8.4 modernizations across the Waaseyaa framework: `array_find` substitutions in ingestion test suites and one production planner, `#[\Deprecated]` attribute migration on `FailedJobRepository`, and `json_validate()` precheck swaps in three CLI handlers where `json_decode` is wrapped in catch-only suppression. Zero architectural change. Each work package is a contained mechanical edit verified by the existing PHPUnit + PHPStan + PHP-CS-Fixer toolchain.

## Technical Context

**Language/Version**: PHP 8.4+ (per project `composer.json` and CLAUDE.md)
**Primary Dependencies**: Symfony 7.x components, Doctrine DBAL, PHPUnit 10.5
**Storage**: N/A — no schema or storage touchpoints
**Testing**: PHPUnit 10.5 (unit + integration suites under `tests/` and `packages/*/tests/`), `composer phpstan` (level 5), `composer cs-check` (PHP-CS-Fixer)
**Target Platform**: PHP 8.4 CLI + cli-server (development); production PHP-FPM
**Project Type**: Single-project monorepo (62 PHP packages, namespace `Waaseyaa\*`)
**Performance Goals**: Behavior-preserving — no perf delta expected or required
**Constraints**: No architectural change, no API change, no new cross-layer imports, no dependency edits to `composer.json` files
**Scale/Scope**: ~15 production line edits + ~10 test file edits across `packages/cli/`, `packages/foundation/`, `packages/queue/`, `packages/routing/` (sweep), `packages/access/` (sweep)

## Charter Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Charter mode: compact. Directives DIR-001, DIR-002, DIR-003 acknowledged. No tactics. Languages: php, typescript.

- **DIR-001/002/003**: Standard software-dev-default directives (test-first, quality gates, branch strategy). This mission is a refactor with full pre-existing test coverage on every targeted file — TDD risk is minimal; a regression test is added only if a swap site lacks coverage that proves the prior behavior.
- **Quality Gates**: All targeted sites must pass PHPUnit, PHPStan level 5, and PHP-CS-Fixer after edit. No new violations admissible.
- **Branch Strategy**: Single mission branch off `main`, merge target `main`. Standard Spec Kitty worktree-per-lane during implement.
- **Layer discipline** (Waaseyaa-specific, beyond charter): `bin/check-package-layers` must remain green; mechanical swaps do not introduce new package edges.

**Charter result: PASS.** No violations to track.

## Phase 0: Research

For a mechanical modernization mission, research is bounded and pre-resolved by the 2026-05-10 audit. The following decisions are recorded for `research.md`:

| Decision | Rationale | Alternatives considered |
|---|---|---|
| Use `array_find` (not `array_any`/`array_all`) for first-match lookups | Existing patterns extract a single matched element; `array_find` returns it directly. `array_any` returns bool — wrong shape. | `array_filter` chain (current) — verbose; `foreach` (current) — also verbose. |
| Keep `try { json_decode } catch` where the decoded value IS used downstream | `json_validate` only validates; replacing with `json_validate + json_decode` doubles the parse cost. | Use `json_validate` everywhere — rejected (perf regression). |
| `#[\Deprecated]` attribute KEPT alongside `@deprecated` docblock | IDEs that don't yet read the attribute still see the docblock; PHPStan reads both. Belt-and-suspenders for one release. | Attribute only — rejected, conservative migration. |
| Null-safety check on every `array_find` result | `array_find` returns `null` on miss; prior `array_values(array_filter(...))[0]` raised `Undefined offset`. Behavior is technically not identical, so each site must be inspected for its no-match path. | Trust callers — rejected, brittle. |

No outstanding NEEDS CLARIFICATION items. Phase 0 artifact: `research.md` (concise, captures the four decisions above).

## Phase 1: Design & Contracts

**No data model.** No new entities, no new APIs, no schema. The mission introduces no new types, controllers, routes, or services.

**No contracts.** All edits are within existing function bodies; public APIs unchanged.

**Quickstart.** A short `quickstart.md` describes how to run the verification toolchain post-edit:

```bash
./vendor/bin/phpunit
composer phpstan
composer cs-check
bin/check-package-layers
```

## Project Structure

### Documentation (this feature)

```
kitty-specs/php84-mechanical-modernization-01KR82KT/
├── plan.md              # This file
├── research.md          # Phase 0 — four decisions captured above
├── quickstart.md        # Phase 1 — verification commands
├── spec.md              # Existing
├── meta.json            # Existing
└── tasks/               # Created by /spec-kitty.tasks
```

(`data-model.md` and `contracts/` are intentionally omitted — see Phase 1 above.)

### Source Code (target sites)

```
packages/
├── cli/
│   ├── src/
│   │   ├── Handler/
│   │   │   ├── MigrateDefaultsHandler.php          # FR-007: json_validate
│   │   │   └── FixturePackRefreshHandler.php       # FR-008: json_validate
│   │   ├── Ingestion/
│   │   │   └── SemanticRefreshTriggerPlanner.php   # FR-005: array_find (verify first-match)
│   │   └── Command/
│   │       └── PerformanceCompareCommand.php       # FR-009: json_validate (locate)
│   └── tests/Unit/
│       ├── Ingestion/
│       │   ├── SchemaValidatorTest.php             # FR-001: 7 array_find sites
│       │   └── ValidationGateValidatorTest.php     # FR-003: 1 site
│       └── Command/
│           └── IngestRunCommandTest.php            # FR-004: 1 site
├── foundation/
│   └── tests/Unit/Ingestion/
│       └── PayloadValidatorTest.php                # FR-002: 1 site
├── queue/
│   └── src/
│       └── FailedJobRepository.php                 # FR-006: #[\Deprecated]
├── routing/    # FR-010 sweep (read-only audit; convert if first-match found)
└── access/     # FR-010 sweep
```

**Structure Decision**: Target the existing Waaseyaa monorepo layout. No new files except mission planning artifacts. No directory restructuring.

## Work Package Sketch (preview only — finalized in `/spec-kitty.tasks`)

The plan envisions ~5 work packages, parallelizable across two lanes:

- **WP01 — array_find: ingestion tests** (lane A) — FR-001..004. ~10 sites, all in test files, low blast radius.
- **WP02 — array_find: SemanticRefreshTriggerPlanner** (lane A, after WP01) — FR-005. Production code, requires verifying first-match semantics.
- **WP03 — `#[\Deprecated]` on FailedJobRepository** (lane B) — FR-006. Single class attribute swap.
- **WP04 — `json_validate` swaps in CLI handlers** (lane B) — FR-007..009. Three sites; per-site decision tree.
- **WP05 — Routing/access `array_find` sweep** (sequential, last) — FR-010. Read-mostly audit; produces a follow-up issue if findings warrant a separate mission.

`/spec-kitty.tasks` will materialize these into the canonical WP files with dependencies, lanes, and test plans.

## Complexity Tracking

*No charter violations to track.*

This mission deliberately avoids architectural complexity. The complementary architectural mission `php84-lazy-object-hydration-01KR82KZ` is queued separately — see its spec for the lazy-object track.

## Branch Contract (restated for `/spec-kitty.tasks`)

- **Current branch at plan finish**: `main`
- **Planning/base branch**: `main`
- **Final merge target**: `main`
- **`branch_matches_target`**: `true`

## Next Step

`/spec-kitty.tasks --mission php84-mechanical-modernization-01KR82KT` to materialize the work packages.
