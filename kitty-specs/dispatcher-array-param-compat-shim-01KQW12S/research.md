# Research: Dispatcher Array-Param Compatibility Shim

**Mission**: `dispatcher-array-param-compat-shim-01KQW12S`
**Phase**: 0 (research)
**Created**: 2026-05-05
**Tracking issue**: [waaseyaa/framework#1390](https://github.com/waaseyaa/framework/issues/1390)

---

## 1. Scope

This research phase resolves the open implementation questions left by `spec.md` so the planning phase can produce a concrete file map without further codebase exploration. All decisions below are sourced from the live tree at `main` (alpha.172) and recorded in `research/evidence-log.csv` with file:line references.

## 2. Key Findings

### 2.1 Single rejection site, single caller

`AppParameterBindingBuilder::buildForParameter()` at `packages/ssr/src/Http/AppController/AppParameterBindingBuilder.php:147–152` is the **only** dispatcher rejection site for unannotated array parameters. A repo-wide grep for the exception message `'array parameters require'` returns one match. The shim only needs to land in this one place.

The builder has exactly **one caller**: `Waaseyaa\SSR\Http\AppController\AppControllerMethodInvoker` at `packages/ssr/src/Http/AppController/AppControllerMethodInvoker.php`. The invoker constructs the builder inline (`new AppParameterBindingBuilder()` with no arguments) and stores it in a `private readonly` property. No service-container registration; no other consumer of the builder class.

### 2.2 Spec cache is per-request and route-fingerprint-keyed — natural dedup

`AppControllerMethodInvoker` maintains a static `private static array $specCache` keyed by `class::method\0routeName\0fingerprint`. Within a single PHP request lifetime, `build()` is invoked at most once per `(controller class, method, route)` triple — subsequent dispatches hit the cache. **This means per-request dedup of the deprecation signal is achieved naturally if we emit during `build()`.** No separate dedup memo is needed in the builder.

Important consequence: across multiple requests in the same process (e.g. tests, long-lived workers), the cache is shared (static) but PHP's single-request, shared-nothing CLI/CGI/FPM model means each request starts cold in practice. For PHP-FPM and `cli-server`, the cache is request-scoped. For long-lived workers (PHP-PM, RoadRunner, FrankenPHP, Swoole), the cache straddles requests; the deprecation signal then emits only on the *first* request that exercises a given (class, method, route) — which is the correct behaviour: emit once per registration, not once per request, when the cache is hot.

This resolves spec.md's NFR-002 ("Deduplicated per request") and FR-004 ("exactly one log line per triple per request"): we emit during `build()`, the cache makes "per build" naturally equivalent to "per (class::method, parameter) per request" under normal SAPIs, and we adjust the spec language to match (see § 6 Open Questions Resolved).

### 2.3 Logger is not currently wired through to the builder

`AppControllerMethodInvoker` does **not** accept a `LoggerInterface`. It is constructed by `SsrPageHandler` (which itself accepts `?LoggerInterface $logger = null` and falls back to `NullLogger`). The invoker is instantiated in `SsrPageHandler::__construct()` without a logger today.

**Decision (R-001):** add an optional `?LoggerInterface $logger = null` parameter to `AppParameterBindingBuilder::__construct()` mirroring the project pattern at `packages/ssr/src/SsrPageHandler.php` and `packages/ssr/src/Http/Twig/TwigErrorPageRenderer.php`. The invoker's inline `new AppParameterBindingBuilder()` instantiation becomes `new AppParameterBindingBuilder(logger: $logger)` and the invoker grows the same optional logger parameter, threaded from `SsrPageHandler`. All three classes already follow the `?LoggerInterface $logger = null → $this->logger = $logger ?? new NullLogger()` idiom, so the seam is clean.

This makes FR-005 ("emission falls through silently when no logger wired") trivially satisfied: the default `NullLogger` is the silent fallback.

### 2.4 Marker attributes — no constructor coupling needed

`Waaseyaa\SSR\Attribute\MapRoute` and `Waaseyaa\SSR\Attribute\MapQuery` are `final readonly class` marker attributes with no constructor parameters and `\Attribute::TARGET_PARAMETER` target. The shim does not need to instantiate the attribute classes — it short-circuits directly to `AppParameterBindingSpec(kind: AppParameterKind::MapRoute, ...)` / `AppParameterKind::MapQuery`, which is exactly what the existing attribute branch (lines 112–126) already does. Mechanically, the shim is a name-keyed alternative entry into the existing branch.

### 2.5 `AppParameterKind` enum already covers the targets

The enum (`packages/ssr/src/Http/AppController/AppParameterKind.php`) declares: `FrameworkService`, `RouteEntity`, `RouteScalar`, `RouteEnum`, `MapRoute`, `MapQuery`, `Custom`. `MapRoute` and `MapQuery` are present and are the correct targets for the shim's two cases.

### 2.6 Test surface — partial; we add one unit + one integration

Under `packages/ssr/tests/`:

- `Unit/Http/Router/AppControllerRouterTest.php` exercises router-level dispatch but does not cover `AppParameterBindingBuilder` directly.
- No existing `AppParameterBindingBuilderTest`. No existing `AppControllerMethodInvokerTest`.

**Decision (R-002):** create a new unit test class at `packages/ssr/tests/Unit/Http/AppController/AppParameterBindingBuilderTest.php` covering the shim cases (FR-001 through FR-005, FR-010), and a new integration test at `packages/ssr/tests/Integration/AppControllerImplicitArrayDispatchTest.php` (or extend `AppControllerRouterTest` with a sibling) that boots a minimal route + controller using the implicit signature and asserts a successful dispatch — covering FR-007 / SC-001.

Both test files use the existing PHPUnit configuration. No new fixtures beyond an inline test-only controller class.

### 2.7 `LoggerInterface` source

The canonical logger contract is `Waaseyaa\Foundation\Log\LoggerInterface` with `Waaseyaa\Foundation\Log\NullLogger` as the no-op implementation. Both are already imported across `packages/ssr/`. CLAUDE.md confirms: "No psr/log. Use `Waaseyaa\Foundation\Log\LoggerInterface` for structured logging."

## 3. Decisions Recorded

| ID    | Decision                                                                                                                                  | Rationale                                                                                                          |
|-------|-------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------|
| R-001 | Add optional `?LoggerInterface $logger = null` to both `AppParameterBindingBuilder::__construct()` and `AppControllerMethodInvoker::__construct()`. Thread from `SsrPageHandler`. | Mirrors existing pattern in `SsrPageHandler` and `TwigErrorPageRenderer`. Zero behaviour change for callers that don't pass one.            |
| R-002 | Add `AppParameterBindingBuilderTest` (unit) and one integration test for the implicit signature.                                          | No existing builder test; integration test is required by SC-001 / FR-007.                                         |
| R-003 | Shim is name-keyed (`'params'` / `'query'`), not type-keyed.                                                                              | Matches the historical implicit signature exactly (`array $params, array $query, AccountInterface $account, HttpRequest $request`). Type-keyed expansion would broaden the contract. |
| R-004 | Emit deprecation signal during `build()`, relying on the invoker's spec cache for natural dedup.                                          | Cache is keyed by `class::method\0routeName\0fingerprint` — equivalent to per-(class,method,route)-per-request dedup. No second memo needed. |
| R-005 | Use structured log payload: `['controller_class' => $class, 'method_name' => $method, 'parameter_name' => $name, 'recommended_attribute' => $attr]` at `notice` level with message `Controller method uses implicit array parameter — add #[MapRoute] or #[MapQuery]`. | `notice` matches "deprecation, not error". Structured payload matches FR-004 contract. |
| R-006 | The builder name-check examines `$parameter->getName()` exactly equal to `'params'` (→ MapRoute) or `'query'` (→ MapQuery). Any other name in the array branch falls through to the existing exception. | Conservative scope; matches issue #1390 prescription verbatim.                                                     |
| R-007 | Spec NFR-002 / FR-004 wording adjusted to "deduplicated per dispatcher build, which under normal SAPIs is per (class::method, parameter) per request and under long-lived workers is per registration." Cache layer documented in PHPDoc on the emission method (FR-009). | Reflects actual semantics; closes spec.md's open question about cache layer.                                       |

## 4. Files Touched (planned)

| Path                                                                                       | Change                                                                                                            |
|--------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------|
| `packages/ssr/src/Http/AppController/AppParameterBindingBuilder.php`                       | Add optional logger ctor param; insert name-keyed shim branch in `buildForParameter()`; add private emission helper with PHPDoc. |
| `packages/ssr/src/Http/AppController/AppControllerMethodInvoker.php`                       | Add optional logger ctor param; pass to builder.                                                                  |
| `packages/ssr/src/SsrPageHandler.php`                                                      | Pass existing `$this->logger` through to the invoker constructor.                                                 |
| `packages/ssr/tests/Unit/Http/AppController/AppParameterBindingBuilderTest.php`            | New test class — covers FR-001 through FR-005, FR-010, R-005.                                                     |
| `packages/ssr/tests/Integration/AppControllerImplicitArrayDispatchTest.php`                | New integration test — covers SC-001, FR-007.                                                                     |
| `CHANGELOG.md`                                                                             | New `[Unreleased]` bullet referencing #1390 (FR-008).                                                              |
| `kitty-specs/dispatcher-array-param-compat-shim-01KQW12S/data-model.md`                    | Documents the AppParameterBindingSpec → AppParameterKind mapping for the shim.                                    |
| `kitty-specs/dispatcher-array-param-compat-shim-01KQW12S/research/evidence-log.csv`        | Source citations.                                                                                                  |
| `kitty-specs/dispatcher-array-param-compat-shim-01KQW12S/research/source-register.csv`     | Reference register.                                                                                                |

No `composer.json` changes. No new package. No layer crossings — all changes inside `packages/ssr/`.

## 5. Risks Re-evaluated Post-Research

| Spec risk                                                                          | Status after research                                                                                                              |
|------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------|
| Higher-level cache means dedup-per-request actually means dedup-per-process.       | **Confirmed and accepted.** The static spec cache in `AppControllerMethodInvoker` is process-scoped under long-lived workers and request-scoped under FPM/CLI. R-007 documents the semantics; this is the desired behaviour, not a bug.        |
| `LoggerInterface` not available at builder construction.                           | **Resolved by R-001.** Optional injection + `NullLogger` fallback. FR-005 trivially satisfied.                                     |
| Layer discipline drift if fix touches `packages/api/`.                             | **Not applicable.** All planned changes are inside `packages/ssr/`. No layer rules invoked.                                        |
| Strict array-name shim accidentally rejects a legitimate `array $params` constructor-injected dependency. | **Not applicable.** The builder operates on `\ReflectionMethod::getParameters()` of the action method, not constructor parameters. Action parameters are dispatch-bound by definition. |

## 6. Open Questions Resolved

The spec recorded none. Research surfaces and resolves one inline:

- **Q (implied by NFR-002 / spec § 12 caveat):** What is the exact dedup tier when emitting the deprecation signal?
- **A (R-007):** Per dispatcher build, which equals per `(class::method, parameter)` per request under FPM/CLI and per registration under long-lived workers. The same emission semantics serve both deployment models.

## 7. References

See `research/evidence-log.csv` and `research/source-register.csv` for full file:line citations.
