---
work_package_id: WP01
title: Implement name-keyed array-param compatibility shim with deprecation logging
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-005
- FR-006
- FR-007
- FR-008
- FR-009
- FR-010
- NFR-001
- NFR-002
- NFR-003
- NFR-004
- NFR-005
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-dispatcher-array-param-compat-shim-01KQW12S
base_commit: 4a7aa56bc559a607dbee288f0eee48ee308baa9d
created_at: '2026-05-05T13:09:29.318652+00:00'
subtasks:
- T001
- T002
- T003
- T004
- T005
- T006
agent: claude
shell_pid: '72450'
history:
- '2026-05-05: created'
authoritative_surface: packages/ssr/src/Http/AppController/
execution_mode: code_change
mission_id: 01KQW12S3R4TY0563R4ZSQ2QBC
mission_slug: dispatcher-array-param-compat-shim-01KQW12S
owned_files:
- packages/ssr/src/Http/AppController/AppParameterBindingBuilder.php
- packages/ssr/src/Http/AppController/AppControllerMethodInvoker.php
- packages/ssr/src/SsrPageHandler.php
- packages/ssr/tests/Unit/Http/AppController/AppParameterBindingBuilderTest.php
- packages/ssr/tests/Integration/AppControllerImplicitArrayDispatchTest.php
- CHANGELOG.md
tags: []
---

# WP01 — Implement name-keyed array-param compatibility shim with deprecation logging

## Objective

Restore the alpha.170 implicit-array controller signature by adding a name-keyed compatibility shim to `Waaseyaa\SSR\Http\AppController\AppParameterBindingBuilder`. Unannotated `array $params` is treated as `#[MapRoute]`; unannotated `array $query` is treated as `#[MapQuery]`. Each shim hit emits one structured `LoggerInterface::notice` per `(class::method, parameter)` per dispatcher build (deduplicated naturally by the existing static spec cache in `AppControllerMethodInvoker`). Other unannotated `array` parameter names continue to raise `InvalidAppControllerBindingException`.

This WP unblocks both Minoo's frozen alpha.171→172 upgrade mission and the `post-1390-dispatcher-reconciliation` mission's WP02–WP04.

## Context

Framework issue [#1390](https://github.com/waaseyaa/framework/issues/1390) reports that alpha.171/172 introduced a hard contract break: `AppParameterBindingBuilder::buildForParameter()` (line 147–152) throws `InvalidAppControllerBindingException` when it encounters an unannotated `array` parameter. Up through alpha.170 the canonical controller signature was `function show(array $params, array $query, AccountInterface $account, HttpRequest $request)`. Bumping any consumer to alpha.171/172 surfaces a runtime 500 on every public route that dispatches such a controller (184 methods across 37 files in Minoo alone).

Read first:

- `kitty-specs/dispatcher-array-param-compat-shim-01KQW12S/spec.md` — full functional spec.
- `kitty-specs/dispatcher-array-param-compat-shim-01KQW12S/plan.md` — implementation plan with the file map.
- `kitty-specs/dispatcher-array-param-compat-shim-01KQW12S/research.md` — decisions R-001..R-007.
- `kitty-specs/dispatcher-array-param-compat-shim-01KQW12S/data-model.md` — `AppParameterBindingSpec` mapping + log payload shape.
- `packages/ssr/src/Http/AppController/AppParameterBindingBuilder.php` — current rejection at line 147.
- `packages/ssr/src/Http/AppController/AppControllerMethodInvoker.php` — sole caller; owns the `$specCache`.
- `packages/ssr/src/SsrPageHandler.php` — top-level dispatcher; already accepts `?LoggerInterface $logger = null`.
- `packages/ssr/src/Http/Twig/TwigErrorPageRenderer.php` — second example of the optional-logger ctor pattern (lines 25–28).

## Branch Strategy

- Planning/base branch: `main`.
- Final merge target: `main`.
- This WP runs in its lane worktree allocated by `lanes.json` after `finalize-tasks`. Stay inside that worktree for the duration of the WP.

## Subtasks

### T001 — Logger threading

**File**: `packages/ssr/src/Http/AppController/AppParameterBindingBuilder.php`

Add an optional logger constructor mirroring the existing `SsrPageHandler` pattern:

```php
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;

final class AppParameterBindingBuilder
{
    private readonly LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function build(...): array { /* unchanged */ }
}
```

**File**: `packages/ssr/src/Http/AppController/AppControllerMethodInvoker.php`

- Add `?LoggerInterface $logger = null` to the constructor.
- Initialize `$this->logger = $logger ?? new NullLogger()`.
- Replace `private readonly AppParameterBindingBuilder $bindingBuilder = new AppParameterBindingBuilder()` with explicit construction in the constructor body so the builder receives `$this->logger`. (Promoted defaults cannot reference `$this`.)

**File**: `packages/ssr/src/SsrPageHandler.php`

Pass the existing `$this->logger` into the invoker constructor at the wiring point. No new constructor parameter needed on `SsrPageHandler` — it already has the logger.

### T002 — Shim branch + emission helper

**File**: `packages/ssr/src/Http/AppController/AppParameterBindingBuilder.php` (`buildForParameter()` method, around line 146–164)

Replace the existing array-rejection block:

```php
if ($named->isBuiltin()) {
    if ($typeName === 'array') {
        throw new InvalidAppControllerBindingException(sprintf(
            'Parameter $%s: array parameters require #[MapRoute] or #[MapQuery].',
            $name,
        ));
    }
    return $this->buildScalarSpec(...);
}
```

with:

```php
if ($named->isBuiltin()) {
    if ($typeName === 'array') {
        if ($name === 'params') {
            $this->emitImplicitArrayDeprecation($method, $parameter, '#[MapRoute]');
            return new AppParameterBindingSpec(
                index: $index,
                kind: AppParameterKind::MapRoute,
            );
        }
        if ($name === 'query') {
            $this->emitImplicitArrayDeprecation($method, $parameter, '#[MapQuery]');
            return new AppParameterBindingSpec(
                index: $index,
                kind: AppParameterKind::MapQuery,
            );
        }
        throw new InvalidAppControllerBindingException(sprintf(
            'Parameter $%s: array parameters require #[MapRoute] or #[MapQuery].',
            $name,
        ));
    }
    return $this->buildScalarSpec(...);
}
```

Add a private helper at the bottom of the class:

```php
/**
 * Emit a structured deprecation signal when the implicit-array shim fires.
 *
 * Payload contract (parsed by consumer tooling):
 * - `controller_class` (string, FQCN) — declaring class of the action
 * - `method_name` (string) — action method name
 * - `parameter_name` ('params'|'query') — which implicit parameter triggered the shim
 * - `recommended_attribute` ('#[MapRoute]'|'#[MapQuery]') — attribute the author should add
 *
 * Dedup is achieved by the static spec cache in AppControllerMethodInvoker
 * (key: `class::method\0routeName\0fingerprint`). Emission therefore occurs
 * at most once per (controller, method, route) per request lifetime under
 * FPM/CLI SAPIs, and at most once per registration under long-lived workers
 * (PHP-PM, RoadRunner, FrankenPHP, Swoole) — the desired behaviour.
 */
private function emitImplicitArrayDeprecation(
    \ReflectionMethod $method,
    \ReflectionParameter $parameter,
    string $recommendedAttribute,
): void {
    $this->logger->notice(
        'Controller method uses implicit array parameter — add #[MapRoute] or #[MapQuery]',
        [
            'controller_class' => $method->getDeclaringClass()->getName(),
            'method_name' => $method->getName(),
            'parameter_name' => $parameter->getName(),
            'recommended_attribute' => $recommendedAttribute,
        ],
    );
}
```

### T003 — Unit tests

**New file**: `packages/ssr/tests/Unit/Http/AppController/AppParameterBindingBuilderTest.php`

Use a recording logger (private inline anonymous class implementing `LoggerInterface`) that captures `(level, message, context)` triples. Test cases (each is a separate `#[Test]` method):

| # | Test                                                                                              | Asserts                                                                                                                          |
|---|---------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------|
| 1 | `array $params` (no attribute) on a fixture method                                                | spec kind = `AppParameterKind::MapRoute`; recording logger captured exactly 1 entry with level=`notice` and the four context keys. |
| 2 | `array $query` (no attribute)                                                                     | spec kind = `AppParameterKind::MapQuery`; recording logger captured exactly 1 entry; `parameter_name` = `'query'`; `recommended_attribute` = `'#[MapQuery]'`. |
| 3 | `#[MapRoute] array $params`                                                                       | spec kind = `MapRoute`; recording logger captured zero entries.                                                                  |
| 4 | `#[MapQuery] array $query`                                                                        | spec kind = `MapQuery`; recording logger captured zero entries.                                                                  |
| 5 | `array $headers` (no attribute, name not in shim list)                                            | `InvalidAppControllerBindingException` thrown with message containing `array parameters require #[MapRoute] or #[MapQuery]`.     |
| 6 | Method with only `array $params` (no `$query`)                                                    | shim applies per-parameter; spec for `$params` is `MapRoute`; logger has 1 entry.                                                |
| 7 | Method with only `array $query` (no `$params`)                                                    | shim applies per-parameter; spec for `$query` is `MapQuery`; logger has 1 entry.                                                 |
| 8 | `?array $params = null` (nullable with default)                                                   | shim applies; spec kind = `MapRoute`; logger has 1 entry.                                                                        |
| 9 | Builder constructed with no logger (`new AppParameterBindingBuilder()`)                            | `$params` shim returns correct spec; no exception (`NullLogger` swallows).                                                        |

Fixture methods can be defined as private methods on the test class itself (or as a private inline anonymous class), reflected via `ReflectionMethod`. The route argument is a minimal `Symfony\Component\Routing\Route('/test')` — the existing builder logic does not require route variables for the shim path.

### T004 — Integration test

**New file**: `packages/ssr/tests/Integration/AppControllerImplicitArrayDispatchTest.php`

Boot a minimal kernel using the existing test bootstrap pattern (mirror `packages/ssr/tests/Unit/Http/Router/AppControllerRouterTest.php`'s setup if applicable). Register a single test-only controller with the historical implicit signature:

```php
public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
{
    return new Response('ok-' . ($params['slug'] ?? 'none'));
}
```

Register a route at `/show/{slug}` pointing at it. Inject a recording logger.

Assertions:

- HTTP response status is 200 (or whatever the fixture controller returns — choose 200 with `'ok'` body).
- Response body equals `ok-foo` for path `/show/foo`.
- Recording logger captured exactly **two** notice entries: one with `parameter_name='params'`, one with `parameter_name='query'`.
- Both entries reference the fixture controller class and `show` method.

### T005 — CHANGELOG entry

**File**: `CHANGELOG.md`

Append to the `[Unreleased]` section under `### Fixed` (or `### Changed` if reviewer prefers — `Fixed` is the better fit since this restores prior behaviour):

```
- Restore implicit-array controller signature compatibility (#1390). Unannotated `array $params` and `array $query` controller parameters now default to `#[MapRoute]` / `#[MapQuery]` semantics with a one-time deprecation log line per registration. Other unannotated `array` parameters still raise `InvalidAppControllerBindingException`. Mirrors the alpha.165 `tenancy:` migration ergonomics. Closes #1390.
```

If the `[Unreleased]` section does not exist, create it at the top above the most recent version heading per Keep a Changelog format.

### T006 — Quality gates

Run from project root, in the lane worktree:

```bash
composer cs-check                # Pint dry-run — style clean
composer phpstan                 # PHPStan level 5 — green
./vendor/bin/phpunit packages/ssr/tests/    # SSR package tests green
./vendor/bin/phpunit             # Full suite green
bin/check-package-layers         # Layer rules green
bin/check-composer-policy        # Composer policy green (no-op for this WP)
```

Fix any blockers before submitting for review. Do **not** disable PHPStan checks or add `phpstan-ignore` comments without explicit justification recorded in the WP history.

## Definition of Done

All of:

- [ ] T001 logger ctor parameters added to both classes; `SsrPageHandler` threads `$this->logger` into the invoker.
- [ ] T002 shim branch + private emission helper landed; PHPDoc on `emitImplicitArrayDeprecation()` documents the payload contract per FR-009.
- [ ] T003 unit test class exists at `packages/ssr/tests/Unit/Http/AppController/AppParameterBindingBuilderTest.php` with 9 test methods covering FR-001 through FR-006, FR-010, and the no-logger path (FR-005).
- [ ] T004 integration test exists at `packages/ssr/tests/Integration/AppControllerImplicitArrayDispatchTest.php` and asserts SC-001 (HTTP 200 on implicit signature) plus the deprecation count (FR-004 / FR-007).
- [ ] T005 CHANGELOG `[Unreleased]` carries the bullet referencing `#1390`.
- [ ] T006 all quality gates green.
- [ ] All spec FRs (FR-001..FR-010) and NFRs (NFR-001..NFR-005) are addressed.
- [ ] All spec SCs (SC-001..SC-006) are met.

## Risks & Mitigations

| Risk                                                                                      | Mitigation                                                                                                                                |
|-------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------|
| Test pollution from static `$specCache` in `AppControllerMethodInvoker`                   | Unit tests instantiate `AppParameterBindingBuilder` directly (no static state). Integration test uses PHPUnit's process boundary.         |
| Long-lived worker dedup means "per registration" not "per request"                        | R-007 documents this is desired. PHPDoc on the emission helper makes the semantics discoverable.                                          |
| A consumer relies on a non-`$params`/`$query` array parameter name                        | Out of scope by design (C-003). The clear `InvalidAppControllerBindingException` and CHANGELOG bullet make the boundary visible.          |
| `?LoggerInterface` accidentally non-optional on the builder ctor                          | Pattern mirrors `SsrPageHandler` and `TwigErrorPageRenderer`. Caught by PHPStan + a unit test that constructs the builder with no args.   |

## Reviewer Guidance

The reviewer should verify:

1. The shim is **name-keyed** (`'params'` / `'query'`), not type-keyed. Any change that broadens to "all unannotated array parameters" is a scope violation (C-003).
2. The deprecation payload contract matches `data-model.md § 3` exactly — four keys, `notice` level, constant message string.
3. PHPDoc on `emitImplicitArrayDeprecation()` documents per-request-vs-per-registration semantics under FPM vs long-lived workers (R-007 / FR-009).
4. No new public surface besides the optional logger ctor parameter on the two classes (NFR-005).
5. All edits stay in `packages/ssr/`. No layer crossings (C-005). `bin/check-package-layers` green.
6. The CHANGELOG bullet sits under `[Unreleased]` (per `feedback_changelog_release_workflow.md`) and references `#1390`.
7. The unit test fixtures are minimal — no real controller subclasses imported from app code.

## After-merge

Per `feedback_pr_traceability_signals.md`:

- Close `#1390` manually with `gh issue close 1390 -c "Resolved by <PR-URL>; deprecation shim restores implicit-array signature."`
- If a release tag has already been cut, edit the GitHub Release notes to include the bullet. Otherwise let `release-cut.yml` promote `[Unreleased]` at the next tag.
- Confirm the `post-1390-dispatcher-reconciliation` mission's WP02–WP04 dependency gate is now clear.

## Activity Log

- 2026-05-05T13:17:47Z – claude – shell_pid=72450 – WP01 implementation complete: shim landed, 10 tests green, all gates clean
