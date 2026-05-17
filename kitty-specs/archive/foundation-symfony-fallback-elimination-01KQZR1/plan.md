# Implementation Plan: Foundation Symfony Fallback Elimination

**Branch**: `main` | **Date**: 2026-05-07 | **Spec**: [spec.md](./spec.md)  
**Mission slug**: `foundation-symfony-fallback-elimination-01KQZR1`  
**Mission ID**: `01KQZR1ELIMSYMFALLBACKPATH01`

## Summary

Remove duplicate kernel-service resolution from the HTTP SSR resolver, decouple `HttpKernel` from Symfony routing exception types for match failures, and establish a single `_controller` contract at the dispatch boundary (normalize in routing or reject with a documented deprecation). Update `docs/specs/infrastructure.md` and tests so the wiring story matches mission **#824**’s typed seams with no shadow fallback maps.

## Technical Context

| Item | Value |
|------|--------|
| Language | PHP 8.4+ |
| Primary packages | `waaseyaa/foundation`, `waaseyaa/routing`, optionally `waaseyaa/ssr` for resolver call sites |
| Testing | PHPUnit (`./vendor/bin/phpunit`), targeted packages under `packages/foundation/tests/` and `packages/routing/tests/` |
| Static analysis | `composer phpstan` |
| Constraints | Layer graph (`bin/check-package-layers`); foundation must not import upward; JSON:API 404/405 bodies remain acceptable to API clients unless a deprecation cycle is documented |

## Charter Check

- No `vendor/` edits.
- Traceability: PR body references this mission slug and/or a tracking GitHub issue when opened.

## Project Structure

### Mission artifacts

```
kitty-specs/foundation-symfony-fallback-elimination-01KQZR1/
├── spec.md
├── plan.md              # this file
├── tasks.md             # WP index + finalize-tasks input
├── quickstart.md
├── contracts/
│   └── resolution-and-dispatch.md
├── artifacts/           # WP01 inventory output
└── tasks/
    └── WP*.md           # per-WP agent prompts
```

### Source (repository)

```
packages/foundation/src/Kernel/HttpKernel.php
packages/foundation/src/Kernel/Http/HttpKernelServiceResolver.php
packages/foundation/src/Kernel/Bootstrap/ProviderRegistryKernelServices.php
packages/foundation/src/Http/ControllerDispatcher.php
packages/routing/src/RouteBuilder.php          # if normalization moves here
packages/ssr/                                  # only if resolver signature or wiring changes
docs/specs/infrastructure.md
CHANGELOG.md
```

## Phases

### Phase 0 — Inventory (WP01)

Produce `artifacts/fallback-inventory.md`: every Symfony-shaped or duplicate-resolution path with file:line, category, and disposition (eliminate / relocate / retain+document). Explicitly exclude dev-admin fallback and PSR-4 manifest scanning unless they duplicate HTTP resolution semantics.

### Phase 1 — Contract (WP02)

Freeze `contracts/resolution-and-dispatch.md` only. Canonical `docs/specs/infrastructure.md` updates land in WP05 after code.

### Phase 2 — Resolver + routing outcomes (WP03)

Single ownership slice: refactor `HttpKernelServiceResolver`; adjust `WaaseyaaRouter::match()` (or agreed adapter) so `HttpKernel` no longer catches Symfony routing exceptions for expected misses; preserve JSON:API 404/405 bodies. Update `HttpKernelTest`, resolver tests, and `WaaseyaaRouterTest`.

### Phase 3 — Controller shape (WP04)

`_controller` contract: `ControllerDispatcher` + `RouteBuilder` + their unit tests.

### Phase 4 — Docs & gates (WP05)

CHANGELOG, `infrastructure.md`, lifecycle drift script (when present), full PHPUnit + PHPStan + CS check; conditional SSR tests if WP03–WP04 touched `packages/ssr`.

## Risks & mitigations

| Risk | Mitigation |
|------|------------|
| External route definitions still use array `_controller` | Deprecation + routing-layer normalization with version note |
| SSR double-resolution or circular DI | Constructor-inject only stable interfaces; run SSR unit tests |

## Open decisions (WP01 → WP02)

1. Whether `WaaseyaaRouter::match()` gains a typed result vs. a dedicated `RouteMatchException` in `waaseyaa/routing` (preferred over importing Symfony’s exceptions in foundation).
2. Whether `HttpKernelServiceResolver` receives `KernelServicesInterface` directly vs. a package-private `KernelServiceLookup` shared with `ProviderRegistryKernelServices`.
