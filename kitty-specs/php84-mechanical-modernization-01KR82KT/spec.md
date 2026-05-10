# PHP 8.4 Mechanical Modernization

**Mission**: php84-mechanical-modernization-01KR82KT
**Type**: software-dev
**Created**: 2026-05-10
**Target branch**: main

## Overview

Apply zero-risk, mechanical PHP 8.4 modernizations to the Waaseyaa framework. Behavior-preserving swaps only. Excludes architectural changes (lazy objects, property hooks) — those are tracked in `php84-lazy-object-hydration-01KR82KZ`.

## Motivation

Waaseyaa requires PHP 8.4+. An audit (2026-05-10) identified concrete sites where modern stdlib calls would replace verbose idioms with no behavioral change. Capturing these as a discrete mission keeps the lazy-object architectural work clean of style noise and gives reviewers a tight, mechanical diff.

## User Scenarios & Testing

### Primary scenario

A framework maintainer reading test code or runtime call sites encounters PHP 8.4-native APIs (`array_find`, `json_validate`, `#[\Deprecated]`) instead of pre-8.4 emulations. Static analyzers and IDEs surface deprecations from attributes rather than docblocks.

### Acceptance scenarios

- All targeted swap sites (Functional Requirements below) compile, lint, and test green on PHP 8.4.
- `composer phpstan`, `composer cs-check`, and the full PHPUnit suite remain green.
- Diff is review-friendly: only the targeted sites change; no incidental edits.

### Edge cases

- `array_find` returns `null` when no element matches — must verify each callsite's null-handling matches the prior `array_values(array_filter(...))[0]` shape (which raised `Undefined offset` on miss).
- `json_validate()` does not return decoded values; sites that need both validation **and** the decoded value must keep the decode call.
- `#[\Deprecated]` emits a runtime `E_USER_DEPRECATED`. Confirm consumers' error handlers don't promote it to a fatal.

## Functional Requirements

| ID | Requirement | Status |
|---|---|---|
| FR-001 | Replace `array_values(array_filter(...))[0]` first-match patterns with `array_find()` in `packages/cli/tests/Unit/Ingestion/SchemaValidatorTest.php` (~7 sites). | Proposed |
| FR-002 | Replace first-match filter pattern with `array_find()` in `packages/foundation/tests/Unit/Ingestion/PayloadValidatorTest.php:618`. | Proposed |
| FR-003 | Replace first-match filter pattern with `array_find()` in `packages/cli/tests/Unit/Ingestion/ValidationGateValidatorTest.php:80`. | Proposed |
| FR-004 | Replace first-match filter pattern with `array_find()` in `packages/cli/tests/Unit/Command/IngestRunCommandTest.php:256`. | Proposed |
| FR-005 | Replace first-match filter pattern with `array_find()` in `packages/cli/src/Ingestion/SemanticRefreshTriggerPlanner.php:415` after verifying first-match semantics. | Proposed |
| FR-006 | Promote `@deprecated` docblock on `packages/queue/src/FailedJobRepository.php` (class-level) to a `#[\Deprecated(message: "Use FailedJobRepositoryInterface", since: "0.1.x")]` attribute; keep the docblock for IDEs that don't read attributes. | Proposed |
| FR-007 | In `packages/cli/src/Handler/MigrateDefaultsHandler.php:236`, replace the catch-only-suppress `try { json_decode } catch { return null }` pattern with a `json_validate()` precheck where the decoded value is not used downstream; if it is used, leave the try/catch and document why. | Proposed |
| FR-008 | Apply the same FR-007 evaluation to `packages/cli/src/Handler/FixturePackRefreshHandler.php:41`. | Proposed |
| FR-009 | Apply the same FR-007 evaluation to `PerformanceCompareCommand` (cli/Perf). | Proposed |
| FR-010 | Sweep `packages/routing/` and `packages/access/` for `foreach { if return }` patterns matching `array_find` semantics; convert any high-confidence sites found. Document non-matches. | Proposed |

## Non-Functional Requirements

| ID | Requirement | Status |
|---|---|---|
| NFR-001 | All existing PHPUnit tests pass after changes (no test count regression; no new skips). | Proposed |
| NFR-002 | `composer phpstan` (level 5) reports zero new errors. | Proposed |
| NFR-003 | `composer cs-check` reports zero new violations. | Proposed |
| NFR-004 | Diff per work package is bounded: each WP touches ≤5 files unless mechanically derivable from a single rule (e.g., one test file with 7 sites = 1 WP). | Proposed |

## Constraints

| ID | Constraint | Status |
|---|---|---|
| C-001 | No behavioral changes. Each swap must be observably equivalent under all inputs the existing tests cover. | Active |
| C-002 | No architectural changes. Lazy objects, property hooks, and asymmetric visibility are out of scope and belong to `php84-lazy-object-hydration`. | Active |
| C-003 | Layer discipline preserved: no new cross-layer imports introduced by mechanical swaps. | Active |
| C-004 | Targets PHP 8.4+. `array_find`, `array_any`, `array_all`, `array_find_key`, `json_validate` (8.3+), and `#[\Deprecated]` (8.4+) are all available. | Active |

## Success Criteria

- 100% of FR-001 through FR-010 either applied or explicitly closed-with-rationale.
- Full test suite green on the merge commit.
- PR diff is reviewable in <15 minutes by a maintainer (no architectural surprises).

## Key Entities

None. This is a pure code-style modernization mission; no new domain concepts.

## Dependencies & Assumptions

- **Assumption**: PHP 8.4 is the minimum supported version per `composer.json` and project rules.
- **Assumption**: The audit findings (2026-05-10, captured in conversation) accurately identify candidate sites; WPs will re-verify each line before editing.
- **Dependency**: None on other in-flight missions. Compatible with other open missions on `main` (no shared file edits anticipated).

## Out of Scope

- Lazy ghost objects for entity hydration (→ `php84-lazy-object-hydration-01KR82KZ`).
- Lazy proxies for `EntityTypeManager` storage factory (→ `php84-lazy-object-hydration-01KR82KZ`).
- Property hooks for future field value-object types (design guidance only; no current candidates).
- Asymmetric visibility on entity IDs (blocked by values-bag architecture).
- HTML5 DOM parser migration (zero candidate sites in framework).
- Implicit nullable parameter cleanup (codebase already clean).

## References

- Audit findings: conversation transcript 2026-05-10 ("Waaseyaa PHP 8.4 Adoption Audit").
- PHP 8.4 release notes: array functions, `#[\Deprecated]`, lazy objects.
- `docs/specs/entity-system.md` (referenced for out-of-scope boundary).
