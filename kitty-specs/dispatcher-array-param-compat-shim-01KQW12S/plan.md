# Implementation Plan: Dispatcher Array-Param Compatibility Shim

**Mission**: `dispatcher-array-param-compat-shim-01KQW12S`
**Branch**: `main` (target) → mission worktree at `kitty/mission-dispatcher-array-param-compat-shim-01KQW12S` (created at `/spec-kitty.implement`)
**Date**: 2026-05-05
**Spec**: [spec.md](spec.md)
**Research**: [research.md](research.md)
**Tracking issue**: [waaseyaa/framework#1390](https://github.com/waaseyaa/framework/issues/1390)

---

## Summary

Restore the alpha.170 implicit-array controller signature (`function show(array $params, array $query, AccountInterface $account, HttpRequest $request)`) by adding a name-keyed compatibility shim to `Waaseyaa\SSR\Http\AppController\AppParameterBindingBuilder`. Unannotated `array $params` defaults to `#[MapRoute]` semantics; unannotated `array $query` defaults to `#[MapQuery]`; any other unannotated `array` parameter still raises `InvalidAppControllerBindingException`. Each shim hit emits one structured `LoggerInterface::notice` per `(class::method, parameter)` per dispatcher build, deduplicated naturally by the existing static spec cache in `AppControllerMethodInvoker`. Logger threads optionally from `SsrPageHandler` via the established `?LoggerInterface $logger = null → NullLogger` pattern.

## Technical Context

| Field                   | Value                                                                                                                                  |
|-------------------------|----------------------------------------------------------------------------------------------------------------------------------------|
| **Language/Version**    | PHP 8.4+                                                                                                                              |
| **Primary Dependencies**| Symfony HttpFoundation, Symfony Routing, Twig (existing — no new deps)                                                                |
| **Storage**             | N/A (no persistent state introduced)                                                                                                  |
| **Testing**             | PHPUnit 10.5 (no `-v`); existing fixtures + `DBALDatabase::createSqlite()` if needed; `recording logger` test double                  |
| **Target Platform**     | All Waaseyaa-hosted SAPIs (FPM, CLI, cli-server, long-lived workers)                                                                  |
| **Project Type**        | Single PHP package modification (`packages/ssr/`); zero cross-layer ripple                                                            |
| **Performance Goals**   | Zero added work per request when no implicit-array params present (NFR-001)                                                           |
| **Constraints**         | PHPStan level 5 clean; PHP-CS-Fixer dry-run clean; `bin/check-package-layers` passes; full PHPUnit suite green                        |
| **Scale/Scope**         | One source class modified, two source classes touched for logger threading, two new test classes, one CHANGELOG entry                |

## Charter Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Charter / Constitution rule                                                              | Compliant?                                                                                                          |
|-------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------|
| Layer discipline (CLAUDE.md § Layer Architecture): no upward imports                      | ✅ All edits inside `packages/ssr/`. No new deps on higher-layer packages. `bin/check-package-layers` runs in CI.    |
| Composer policy (CP002/CP003/CP006)                                                       | ✅ No `composer.json` changes planned. `bin/check-composer-policy` is a no-op for this mission.                     |
| Logging convention (CLAUDE.md § Architecture Gotchas — "No psr/log")                     | ✅ Uses `Waaseyaa\Foundation\Log\LoggerInterface`. No `error_log` / `trigger_error`.                                |
| Modern PHP rules (memory `feedback_modern_php_rules.md`): typed interfaces only           | ✅ Optional ctor params are typed; `final readonly` patterns preserved; no service locators introduced.             |
| Regression-test discipline (memory `feedback_regression_tests.md`)                        | ✅ Two new test classes lock the contract: a unit test (FR-001..FR-005) and an integration test (SC-001).           |
| Workflow (`docs/specs/workflow.md`): substantive work begins in Spec Kitty                | ✅ This mission is the entry point. PR will reference `#1390` and the mission slug.                                |
| Source-over-summary data-freshness                                                        | ✅ All file:line evidence is sourced from live tree at `main` (alpha.172) and recorded in `research/evidence-log.csv`. |

## Project Structure

### Documentation (this mission)

```
kitty-specs/dispatcher-array-param-compat-shim-01KQW12S/
├── plan.md                              # This file
├── spec.md                              # Functional spec (already filled)
├── research.md                          # Phase 0 — decisions R-001..R-007
├── data-model.md                        # Shim binding shape + log payload
├── meta.json                            # Mission metadata + tracking issue link
├── research/
│   ├── evidence-log.csv                 # 14 evidence rows
│   └── source-register.csv              # 9 source records
└── tasks/                               # Filled by /spec-kitty.tasks
```

### Source Code (repository root)

```
packages/ssr/
├── src/
│   ├── Http/
│   │   ├── AppController/
│   │   │   ├── AppParameterBindingBuilder.php       # ← SHIM ADDED
│   │   │   ├── AppParameterBindingSpec.php          # unchanged
│   │   │   ├── AppParameterKind.php                 # unchanged
│   │   │   └── AppControllerMethodInvoker.php       # ← logger threaded through
│   │   └── Twig/
│   │       └── TwigErrorPageRenderer.php            # logger pattern reference, no edit
│   ├── Attribute/
│   │   ├── MapRoute.php                             # unchanged
│   │   └── MapQuery.php                             # unchanged
│   └── SsrPageHandler.php                           # ← passes its $logger to invoker
└── tests/
    ├── Unit/
    │   └── Http/
    │       └── AppController/
    │           └── AppParameterBindingBuilderTest.php   # ← NEW — FR-001..FR-006, FR-010, R-005
    └── Integration/
        └── AppControllerImplicitArrayDispatchTest.php   # ← NEW — SC-001, FR-007

CHANGELOG.md                                         # ← `[Unreleased]` bullet — FR-008
```

**Structure Decision**: Single-package single-purpose mission. All edits land in `packages/ssr/` (the package that owns `AppController` despite its SSR namespace). No new packages, no new public API surface.

## Phase Plan

### Phase 0 — Research (DONE)

Outputs: `research.md`, `data-model.md`, `research/evidence-log.csv`, `research/source-register.csv`.

Decisions R-001 through R-007 close every open question from `spec.md § 12`.

### Phase 1 — Design (this document)

Outputs: this `plan.md`. No `contracts/` or `quickstart.md` directory needed — the contract surface is the existing `AppParameterBindingSpec` shape (no change) plus the structured log payload (documented in `data-model.md § 3`). No external API to publish; no consumer-facing migration script required (consumers see HTTP 200 + log lines).

### Phase 2 — Tasks (next)

Run `/spec-kitty.tasks` to materialize work-package files. Single work package — implementation is small and tightly scoped.

### Phase 3 — Implement

In a worktree at `kitty/mission-dispatcher-array-param-compat-shim-01KQW12S`:

1. **WP01-T001** — Logger threading
   - Add `?LoggerInterface $logger = null` ctor param + `NullLogger` fallback to `AppParameterBindingBuilder`.
   - Add same to `AppControllerMethodInvoker`; pass `$this->logger` into `new AppParameterBindingBuilder(logger: $this->logger)`.
   - Update `SsrPageHandler` to pass its existing `$this->logger` into the invoker constructor.
2. **WP01-T002** — Shim branch
   - In `AppParameterBindingBuilder::buildForParameter()`, before the existing `$typeName === 'array'` throw, add: `if ($parameter->getName() === 'params') { emitDeprecation(...); return new AppParameterBindingSpec(index: $index, kind: AppParameterKind::MapRoute); }` and a parallel branch for `'query'` → `MapQuery`.
   - Add private `emitImplicitArrayDeprecation(\ReflectionMethod $method, \ReflectionParameter $parameter, string $recommendedAttribute): void` with PHPDoc documenting the structured log payload (FR-009).
3. **WP01-T003** — Unit tests
   - New `packages/ssr/tests/Unit/Http/AppController/AppParameterBindingBuilderTest.php`.
   - Coverage matrix:

| Test                                                                | FR(s) covered      |
|---------------------------------------------------------------------|--------------------|
| `array $params` → `MapRoute` spec + 1 log line                      | FR-001, FR-004, FR-009 |
| `array $query` → `MapQuery` spec + 1 log line                       | FR-002, FR-004     |
| `#[MapRoute] array $params` → `MapRoute` spec + 0 log lines         | FR-001, FR-003 inverse |
| `#[MapQuery] array $query` → `MapQuery` spec + 0 log lines          | FR-002, FR-003 inverse |
| `array $headers` (no attribute) → `InvalidAppControllerBindingException` | FR-003             |
| Only `$params`, no `$query` → shim applies per-parameter            | SC-005             |
| Only `$query`, no `$params` → shim applies per-parameter            | SC-005             |
| `?array $params = null` → `MapRoute` spec + 1 log line              | edge case          |
| Builder with no logger → no crash, behavior unchanged               | FR-005             |

Test double: a recording `LoggerInterface` implementation (private inline class in the test file) that captures `(level, message, context)` triples. No external library.

4. **WP01-T004** — Integration test
   - New `packages/ssr/tests/Integration/AppControllerImplicitArrayDispatchTest.php`.
   - Boots a minimal kernel + route + test-only controller using the implicit signature. Asserts HTTP 200 (or whatever the controller returns) and asserts the recording logger captured exactly two lines (one for `$params`, one for `$query`).
   - SC-001, FR-007.
5. **WP01-T005** — CHANGELOG
   - Add to `[Unreleased]` under `### Fixed` (or `### Changed` if reviewer prefers): `Restore implicit-array controller signature compatibility (#1390). Unannotated array $params and array $query now default to #[MapRoute] / #[MapQuery] with a one-time deprecation log line per registration. Other unannotated array parameters still raise InvalidAppControllerBindingException.`
6. **WP01-T006** — Quality gates
   - `composer phpstan` — green
   - `composer cs-check` — green
   - `./vendor/bin/phpunit` — full suite green
   - `bin/check-package-layers` — green
   - `bin/check-composer-policy` — green (no-op)

### Phase 4 — Review

Single review cycle by `independent-reviewer`. Acceptance gates from `spec.md § 11` enforced.

### Phase 5 — Merge

PR title: `fix: restore implicit-array controller signature with deprecation shim (#1390)`
PR body references both the mission slug and `#1390`. Squash-merges to `main`. After merge:

- Close `#1390` manually (`gh issue close 1390 -c "..."`).
- Edit GitHub Release notes if the next alpha tag has already been cut; otherwise let `release-cut.yml` promote the `[Unreleased]` bullet at next tag.
- Confirm `post-1390-dispatcher-reconciliation-01KQTTJS` mission's WP02–WP04 dependency gate is now clear (`#1390` is closed).

## Complexity Tracking

No charter violations. Single-package change; no new abstractions; no new public surface beyond two optional ctor params; no migrations.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|--------------------------------------|
| (none)    | (n/a)      | (n/a)                                |

## Risks & Mitigations (post-research)

| Risk                                                                              | Mitigation                                                                                                              |
|-----------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------|
| Long-lived worker dedup — implicit shim usage flies under the radar after warm-up | R-007 documents this is desired (per-registration, not per-request). PHPDoc on `emitImplicitArrayDeprecation()` makes it discoverable. |
| A consumer relies on a non-`$params`/`$query` array parameter name                | Out of scope by design (C-003). The clear `InvalidAppControllerBindingException` and CHANGELOG bullet make the boundary visible. |
| Test pollution from static `$specCache`                                           | Tests instantiate `AppParameterBindingBuilder` directly without going through the invoker. The builder has no static state. The integration test resets cache via process boundary (each test method = fresh PHP isolation in PHPUnit's default config). |
| Logger ctor parameter accidentally non-optional                                   | Caught by PHPStan + PHPUnit fixture-construction error. Pattern mirrors existing `SsrPageHandler` and `TwigErrorPageRenderer`. |

## Open Questions

None remaining. R-001 through R-007 close them.
