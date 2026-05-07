# Specification: Foundation Symfony Fallback Elimination

**Mission**: `foundation-symfony-fallback-elimination-01KQZR1`  
**Mission ID**: `01KQZR1ELIMSYMFALLBACKPATH01`  
**Mission type**: `software-dev`  
**Created**: 2026-05-07  
**Target branch**: `main`  
**Lineage**: Continues the typed-wiring work from mission **#824** (`KernelServicesInterface`, `HttpServiceResolverInterface`, capability interfaces). `docs/specs/infrastructure.md` already documents the kernel-services bus and the HTTP resolver seam; this mission closes the remaining **implicit** and **Symfony-shaped** branches called out below.

---

## 1. Overview

Waaseyaa foundation HTTP and bootstrap code still tolerates **parallel code paths** that exist only because Symfony components historically owned those edges:

1. **Routing mismatch handling** — `HttpKernel` catches `\Symfony\Component\Routing\Exception\ResourceNotFoundException` and `MethodNotAllowedException` to synthesize JSON:API responses. That couples the kernel to Symfony’s exception type for control flow instead of a Waaseyaa-owned outcome (or a thin adapter produced at the router boundary).

2. **Controller shape normalization** — `ControllerDispatcher` converts Symfony’s `[FQCN, method]` array `_controller` default into the `FQCN::method` string domain routers expect, and treats invokables/closures as a pre-router fallback. The framework’s own `RouteBuilder` already emits string controllers; the array branch exists for routes imported or defined in Symfony-native shapes.

3. **Duplicate kernel-service resolution** — `HttpKernelServiceResolver` special-cases `DatabaseInterface::class` after walking provider bindings, duplicating logic that `ProviderRegistryKernelServices` already implements for the kernel-services bus. That is a second “narrow fallback map” outside `KernelServicesInterface`, contrary to the single-bus narrative in `infrastructure.md`.

4. **Discovery / autoload commentary** — `PackageManifestCompiler` and `Request` alias documentation reference PSR-4 “fallback” scanning; those are **not** Symfony HTTP fallbacks. WP01 must explicitly **exclude** or **reclassify** them so this mission does not sprawl into unrelated performance work unless a genuine duplicate resolution path is found.

This mission **eliminates** category (1)–(3) where feasible without breaking public HTTP contracts, or **replaces** them with explicit Waaseyaa types, facades, or documented single-resolution seams. Where elimination would break third-party route definitions, the mission may introduce a **narrow adapter** at the routing package boundary so `HttpKernel` / `ControllerDispatcher` no longer import Symfony exception types or array-controller heuristics directly.

---

## 2. Goals and success criteria

### Goals

1. **No duplicate kernel-owned DI fallbacks** — HTTP-time resolution of services that already exist on `KernelServicesInterface` does not re-implement `ProviderRegistryKernelServices` logic in `HttpKernelServiceResolver` (or elsewhere in foundation).

2. **Symfony isolation at the routing boundary** — `HttpKernel` does not use Symfony routing exception classes for business-level branching; either the router maps misses to a Waaseyaa result type, or a dedicated adapter translates once.

3. **Controller `_controller` contract** — At the `ControllerDispatcher` entry, `_controller` is either a string `Class::method`, a closure/invokable, or an explicitly documented third shape owned by Waaseyaa — not an ad hoc Symfony array convention handled inside foundation. If array form must remain for BC, it is normalized **before** foundation HTTP (e.g. in `waaseyaa/routing`) with tests owned by that package.

4. **Specs and tests** — `docs/specs/infrastructure.md` (and, if needed, `docs/specs/http-entry-point.md` or routing spec) matches implementation. Contract or unit tests lock the resolution order and prove removed branches stay removed.

### Success criteria

| ID | Criterion |
|----|-----------|
| SC-001 | `HttpKernelServiceResolver` contains **no** special-case returns for types already returned by `KernelServicesInterface::get()` in the default kernel wiring (starting with `DatabaseInterface`). |
| SC-002 | `packages/foundation/src/Kernel/HttpKernel.php` has **no** `catch (\Symfony\Component\Routing\Exception\...)` blocks; routing outcomes use Waaseyaa-owned types or a routing-layer adapter. |
| SC-003 | `ControllerDispatcher` contains **no** `[class, method]` array normalization for `_controller`, **or** the spec documents the single supported import path and tests live beside the producer that emits array controllers. |
| SC-004 | Full `./vendor/bin/phpunit` and `composer phpstan` pass on CI. |
| SC-005 | CHANGELOG `[Unreleased]` describes consumer-visible changes (if any) and migration notes for anyone defining routes outside `RouteBuilder`. |

---

## 3. User scenarios

### Scenario A — Framework maintainer

A maintainer reads `infrastructure.md` and sees one authoritative description of provider resolution, kernel-services delegation, and HTTP-time controller DI — with no “also check this other fallback in `HttpKernelServiceResolver`” footnote.

### Scenario B — SSR / app controller author

SSR continues to resolve constructor parameters via `HttpServiceResolverInterface`; `DatabaseInterface` (and other kernel-owned services) resolve through the same semantic path as `ServiceProvider::resolve()` without silent duplicate maps.

### Scenario C — Package author defining Symfony Route objects

If they still attach array `_controller` defaults, either (a) those routes are normalized when registered, or (b) they receive a clear deprecation with a versioned removal — documented in CHANGELOG and spec.

---

## 4. Functional requirements

| ID | Requirement |
|----|-------------|
| FR-001 | Eliminate or delegate duplicate `DatabaseInterface` (and any other overlapping) resolution between `HttpKernelServiceResolver` and `KernelServicesInterface` implementations used at bootstrap. |
| FR-002 | Replace Symfony routing exception-driven control flow in `HttpKernel` with a Waaseyaa-owned match result or adapter. |
| FR-003 | Remove or relocate Symfony-shaped `_controller` array normalization so foundation’s `ControllerDispatcher` consumes a single contract. |
| FR-004 | Update `docs/specs/infrastructure.md` (and related specs if routing behavior moves) so the HTTP + bootstrap story is internally consistent. |
| FR-005 | Extend or add tests in `packages/foundation/tests/` and, if routing package owns normalization, `packages/routing/tests/` to prevent regression. |

---

## 5. Non-functional requirements

| ID | Requirement |
|----|-------------|
| NFR-001 | No measurable regression in routing or dispatch latency on the default skeleton (micro-benchmark optional; must not add O(n) provider walks per request beyond current). |
| NFR-002 | Layer discipline: foundation must not import upward; any move of logic into `waaseyaa/routing` must respect `bin/check-package-layers`. |
| NFR-003 | PHP 8.4+ style: `declare(strict_types=1)`, typed properties, `final` where appropriate. |

---

## 6. Constraints

| ID | Constraint |
|----|------------|
| C-001 | Do not modify `vendor/`. |
| C-002 | Public JSON:API error shapes for 404/405 from unmatched routes must remain acceptable to existing clients unless a deprecation cycle is documented. |
| C-003 | `auth.dev_fallback_account` (DevAdminAccount) is **out of scope** — it is an intentional product feature, not Symfony framework wiring. |

---

## 7. Out of scope

- Replacing Symfony HttpFoundation `Request` / `Response` types (Waaseyaa already aliases `Request`).
- PSR-4 / classmap discovery fallback in `PackageManifestCompiler` unless WP01 proves it duplicates HTTP resolution semantics.
- Changes to consumer apps (Giiken, Minoo) except documenting required bumps if public extension points change.

---

## 8. Risks

| Risk | Mitigation |
|------|------------|
| Third-party code registers routes with array `_controller` | Deprecation window + routing-layer normalization |
| SSR tests rely on current resolver behavior | Run SSR package tests and Giiken smoke after resolver refactor |

---

## 9. References (source anchors)

- `packages/foundation/src/Kernel/HttpKernel.php` — routing try/catch, `getHttpServiceResolver()`
- `packages/foundation/src/Kernel/Http/HttpKernelServiceResolver.php` — provider walk + `DatabaseInterface` branch
- `packages/foundation/src/Kernel/Bootstrap/ProviderRegistryKernelServices.php` — canonical kernel-services map
- `packages/foundation/src/Http/ControllerDispatcher.php` — array `_controller` normalization
- `docs/specs/infrastructure.md` — ServiceProvider tiers, kernel-services bus, HTTP resolver section
