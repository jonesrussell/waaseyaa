---
work_package_id: WP03
title: HTTP resolver unification and routing outcomes
dependencies:
- WP02
requirement_refs:
- FR-001
- FR-002
- NFR-002
- C-002
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T008
- T009
- T010
- T011
- T012
- T013
history: []
authoritative_surface: packages/foundation/src/Kernel/
execution_mode: code_change
mission_id: 01KQZR1ELIMSYMFALLBACKPATH01
mission_slug: foundation-symfony-fallback-elimination-01KQZR1
owned_files:
- packages/foundation/src/Kernel/HttpKernel.php
- packages/foundation/src/Kernel/Http/HttpKernelServiceResolver.php
- packages/routing/src/WaaseyaaRouter.php
- packages/foundation/tests/Unit/Kernel/HttpKernelTest.php
- packages/foundation/tests/Unit/Kernel/Http/HttpKernelServiceResolverTest.php
- packages/routing/tests/Unit/WaaseyaaRouterTest.php
tags: []
---

# WP03 — HTTP resolver unification and routing outcomes

## Objective

1. **SC-001**: Remove duplicate kernel-owned resolution from `HttpKernelServiceResolver` — delegate per WP02 contract.
2. **SC-002**: `HttpKernel` must not catch Symfony routing exceptions for normal misses — implement outcome at `WaaseyaaRouter::match()` boundary (catch-translate-rethrow as Waaseyaa types) or equivalent per contract.

## Read first

- `artifacts/fallback-inventory.md`
- `contracts/resolution-and-dispatch.md`

## Subtasks

### T008–T010 — Resolver

Implement resolver delegation; extend `HttpKernelServiceResolverTest`; remove dedicated `DatabaseInterface` branch if contract requires.

### T011–T012 — Routing outcomes

Implement `WaaseyaaRouter` (or agreed adapter) so `HttpKernel` receives arrays on success and handles misses/405 without importing `Symfony\Component\Routing\Exception\*` for expected cases. Preserve JSON:API 404/405 bodies from today’s `HttpKernel`.

### T013 — Tests

Update `HttpKernelTest`, `WaaseyaaRouterTest`, and resolver tests for new behavior. Add coverage for 404/405 response shapes.

## Validation

- [ ] `rg 'Symfony\\\\Component\\\\Routing\\\\Exception' packages/foundation/src/Kernel/HttpKernel.php` shows no matches.
- [ ] `./vendor/bin/phpunit` green for the six owned test files at minimum; `composer phpstan` clean for touched production files.
