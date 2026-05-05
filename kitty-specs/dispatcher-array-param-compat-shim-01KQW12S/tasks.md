# Tasks: Dispatcher Array-Param Compatibility Shim

**Mission**: `dispatcher-array-param-compat-shim-01KQW12S`
**Date**: 2026-05-05
**Spec**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md) · **Research**: [research.md](./research.md)
**Tracking issue**: [framework#1390](https://github.com/waaseyaa/framework/issues/1390)

## Branch contract

- Planning/base branch: `main`
- Final merge target: `main`
- WP01 lane worktree at `kitty/mission-dispatcher-array-param-compat-shim-01KQW12S` (allocated at execution time).

## Phase posture

- **Single WP** — implementation, tests, and CHANGELOG land together. No phase split; the shim is a single-package single-class behaviour change.

## Subtask Index

| ID    | Description                                                                                          | WP   | Parallel |
|-------|------------------------------------------------------------------------------------------------------|------|----------|
| T001  | Add optional `?LoggerInterface $logger = null` ctor param to `AppParameterBindingBuilder` and `AppControllerMethodInvoker`; thread from `SsrPageHandler` | WP01 |          |
| T002  | Add name-keyed shim branch in `AppParameterBindingBuilder::buildForParameter()` for `$params` (→ `MapRoute`) and `$query` (→ `MapQuery`); add private `emitImplicitArrayDeprecation()` helper with PHPDoc payload contract | WP01 |          |
| T003  | Add unit test class `packages/ssr/tests/Unit/Http/AppController/AppParameterBindingBuilderTest.php` covering shim cases, attribute parity, name-not-shimmed exception, edge cases, no-logger path | WP01 | [P]      |
| T004  | Add integration test `packages/ssr/tests/Integration/AppControllerImplicitArrayDispatchTest.php` booting a minimal kernel with the historical implicit signature; assert HTTP success + exactly two log lines | WP01 | [P]      |
| T005  | Add `[Unreleased]` CHANGELOG bullet referencing `#1390` and the deprecated implicit-array shape; pointer to `#[MapRoute]` / `#[MapQuery]` migration | WP01 | [P]      |
| T006  | Run quality gates: `composer phpstan`, `composer cs-check`, `./vendor/bin/phpunit`, `bin/check-package-layers`, `bin/check-composer-policy` — fix any blockers | WP01 |          |

The `[P]` marker is reference-only. T003, T004, T005 touch independent files and can run in parallel within the WP. T002 depends on T001 (logger needs to exist before the emission call site references it). T006 runs last.

## Work Packages

### WP01 — Implement name-keyed array-param compatibility shim with deprecation logging

**Goal**: Restore the alpha.170 implicit-array controller signature via a name-keyed compatibility shim in `AppParameterBindingBuilder`. Unannotated `array $params` defaults to `#[MapRoute]`; unannotated `array $query` defaults to `#[MapQuery]`. Each shim hit emits one structured `LoggerInterface::notice` per registration with `(controller_class, method_name, parameter_name, recommended_attribute)` context. Other unannotated `array` parameters still raise `InvalidAppControllerBindingException`.

**Priority**: P0 (unblocks Minoo's frozen alpha.171→172 upgrade and clears the `post-1390-dispatcher-reconciliation` mission's WP02–WP04 gate).
**Independent test**: A controller method with the historical signature `function show(array $params, array $query, AccountInterface $account, HttpRequest $request)` registered against a route returns HTTP 200 (or whatever the controller returns) and emits exactly two log lines per request — one for `$params`, one for `$query`. A method with `array $headers` (no attribute) still throws.
**Estimated prompt size**: ~350 lines.
**Execution mode**: `code_change`.
**Owned files**:

- `packages/ssr/src/Http/AppController/AppParameterBindingBuilder.php`
- `packages/ssr/src/Http/AppController/AppControllerMethodInvoker.php`
- `packages/ssr/src/SsrPageHandler.php`
- `packages/ssr/tests/Unit/Http/AppController/AppParameterBindingBuilderTest.php` (new)
- `packages/ssr/tests/Integration/AppControllerImplicitArrayDispatchTest.php` (new)
- `CHANGELOG.md`

**Authoritative surface**: `packages/ssr/src/Http/AppController/`.

**Requirement refs**: FR-001, FR-002, FR-003, FR-004, FR-005, FR-006, FR-007, FR-008, FR-009, FR-010, NFR-001, NFR-002, NFR-003, NFR-004, NFR-005, C-001, C-002, C-003, C-004, C-005, C-006, C-007, C-008.

Tracking:

- [ ] T001 Add optional `?LoggerInterface $logger = null` ctor param to `AppParameterBindingBuilder` and `AppControllerMethodInvoker`; thread from `SsrPageHandler` (WP01)
- [ ] T002 Add name-keyed shim branch in `AppParameterBindingBuilder::buildForParameter()` for `$params` (→ `MapRoute`) and `$query` (→ `MapQuery`); add private `emitImplicitArrayDeprecation()` helper with PHPDoc payload contract (WP01)
- [ ] T003 Add unit test class `packages/ssr/tests/Unit/Http/AppController/AppParameterBindingBuilderTest.php` covering shim cases, attribute parity, name-not-shimmed exception, edge cases, no-logger path (WP01)
- [ ] T004 Add integration test `packages/ssr/tests/Integration/AppControllerImplicitArrayDispatchTest.php` booting a minimal kernel with the historical implicit signature; assert HTTP success + exactly two log lines (WP01)
- [ ] T005 Add `[Unreleased]` CHANGELOG bullet referencing `#1390` and the deprecated implicit-array shape; pointer to `#[MapRoute]` / `#[MapQuery]` migration (WP01)
- [ ] T006 Run quality gates: `composer phpstan`, `composer cs-check`, `./vendor/bin/phpunit`, `bin/check-package-layers`, `bin/check-composer-policy` — fix any blockers (WP01)

Implementation sketch: thread logger → add shim branch + emission helper → write unit tests → write integration test → CHANGELOG → run all gates → commit.

Dependencies: none. This mission can run immediately on `main`.

Risks:
- Test pollution from static `$specCache` in `AppControllerMethodInvoker` — mitigated by having unit tests instantiate the builder directly (which has no static state) and integration tests rely on PHPUnit's process boundary.
- Cache layer makes "per request" become "per registration" under long-lived workers — documented behaviour per research R-007; PHPDoc on the emission helper makes the semantics discoverable.

Prompt: [tasks/WP01-array-param-compat-shim.md](./tasks/WP01-array-param-compat-shim.md) (filled by `tasks-packages` step).
