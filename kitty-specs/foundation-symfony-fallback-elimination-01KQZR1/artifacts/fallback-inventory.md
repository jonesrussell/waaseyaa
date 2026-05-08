# Fallback inventory — foundation Symfony wiring

**Mission**: `foundation-symfony-fallback-elimination-01KQZR1`  
**WP**: WP01  
**Date**: 2026-05-07

## Scope rules

- **In scope**: HTTP dispatch, provider resolution duplication, Symfony-shaped `_controller` / routing control flow in foundation + routing packages.
- **Explicitly out of scope**: `auth.dev_fallback_account` / `DevAdminAccount` (product feature, spec C-003); `PackageManifestCompiler` PSR-4 classmap “fallback” (autoload discovery, not HTTP DI).

## Inventory

| # | File | Region | Category | Notes | Disposition |
|---|------|--------|----------|-------|---------------|
| 1 | `packages/foundation/src/Kernel/HttpKernel.php` | `serveHttpRequest()` → `try { $router->match($path) } catch (Symfony ResourceNotFoundException \| MethodNotAllowedException)` | Symfony exception control flow | Expected misses use Symfony’s exception types at foundation boundary | **Eliminate** — translate inside `WaaseyaaRouter::match()` (or wrapper) to Waaseyaa-owned outcome; `HttpKernel` handles typed result only |
| 2 | `packages/foundation/src/Kernel/Http/HttpKernelServiceResolver.php` | After provider loop: `if ($className === DatabaseInterface::class) return $this->database` | Duplicate kernel-service map | Same concern as `ProviderRegistryKernelServices::get()` for `DatabaseInterface` | **Eliminate** — delegate to `KernelServicesInterface` or shared lookup (contract WP02) |
| 3 | `packages/foundation/src/Http/ControllerDispatcher.php` | (removed) array → string normalization | Symfony Route `_controller` shape | **Done (WP04):** `RouteBuilder` + `HttpKernel` use `RouteBuilder::normalizeControllerDefault()` |
| 4 | `packages/routing/src/WaaseyaaRouter.php` | `match()` docblock `@throws Symfony\Component\Routing\Exception\...` + direct `UrlMatcher::match()` | Symfony throws surface | Natural propagation to HttpKernel today | **Retain boundary, change behavior** — catch inside `WaaseyaaRouter` (or thin helper) and map to result type / internal exception not re-exporting Symfony types to foundation |
| 5 | `packages/foundation/src/Kernel/Bootstrap/ProviderRegistryKernelServices.php` | `get()` if-chain for core abstracts | Canonical kernel bus | Not a fallback to remove; **reference implementation** for resolver delegation | **Retain** — used as single semantic source for WP03 |

## SSR / HTTP resolver consumers (appendix)

| Call site kind | Path pattern | Notes |
|----------------|--------------|-------|
| SSR app-controller binding | `packages/ssr/` uses `HttpKernel::getHttpServiceResolver()` / `HttpServiceResolverInterface` | Inventory row #2 affects constructor DI for reflected controller params; run SSR tests after WP03 |

## Layer notes

- `bin/check-package-layers`: routing is L4, foundation L0 — moving Symfony exception translation into `WaaseyaaRouter` keeps Symfony types out of **foundation** while routing may still use Symfony `UrlMatcher` internally (acceptable).

## Disposition summary

| Disposition | Count |
|-------------|-------|
| Eliminate | 2 |
| Relocate or deprecate (contract choice) | 1 |
| Retain / boundary change | 2 |
