---
work_package_id: WP01
title: Fallback inventory and disposition
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-003
- C-001
- C-002
- C-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T001
- T002
- T003
- T004
mission_id: 01KQZR1ELIMSYMFALLBACKPATH01
mission_slug: foundation-symfony-fallback-elimination-01KQZR1
authoritative_surface: kitty-specs/foundation-symfony-fallback-elimination-01KQZR1/artifacts/
execution_mode: planning_artifact
owned_files:
- kitty-specs/foundation-symfony-fallback-elimination-01KQZR1/artifacts/**
tags: []
---

# WP01 — Fallback inventory and disposition

## Objective

Produce **`artifacts/fallback-inventory.md`** — the authoritative list of Symfony-shaped and duplicate-resolution paths — with a disposition per row. **No production PHP edits** in this WP.

## Read first

- `kitty-specs/foundation-symfony-fallback-elimination-01KQZR1/spec.md`
- `kitty-specs/foundation-symfony-fallback-elimination-01KQZR1/plan.md`
- `docs/specs/infrastructure.md` (kernel-services bus + HTTP resolver sections)

## Subtasks

### T001 — Code path read pass

1. Read `packages/foundation/src/Kernel/HttpKernel.php` (routing `match`, exception handling, `getHttpServiceResolver`).
2. Read `packages/foundation/src/Kernel/Http/HttpKernelServiceResolver.php` full file.
3. Read `packages/foundation/src/Kernel/Bootstrap/ProviderRegistryKernelServices.php` full file.
4. Read `packages/foundation/src/Http/ControllerDispatcher.php` (controller normalization + callable branch).
5. Skim `packages/routing/src/` for `WaaseyaaRouter`, matcher, and `_controller` defaults.

### T002 — Write inventory artifact

Create `kitty-specs/foundation-symfony-fallback-elimination-01KQZR1/artifacts/fallback-inventory.md` with a table:

| # | File | Symbol / region | Category | Notes | Disposition (eliminate / relocate / retain+document) |

Cover at minimum: HttpKernel Symfony routing catches; `HttpKernelServiceResolver` `DatabaseInterface` branch; `[class, method]` normalization; any other duplicate `KernelServices`-shaped resolution found in T001.

### T003 — SSR surfaces

Grep `packages/ssr` for `getHttpServiceResolver`, `HttpServiceResolverInterface`, `HttpKernelServiceResolver`. Record call sites in the inventory appendix.

### T004 — Layer constraints

Run or read `bin/check-package-layers` output relevant to `foundation` ↔ `routing`. Note in inventory whether WP04/WP05 require routing-owned types to avoid upward imports.

## Validation

- [ ] `artifacts/fallback-inventory.md` committed with this WP (use `git add -f …/artifacts/fallback-inventory.md` — root `.gitignore` ignores `artifacts/`).
- [ ] Every spec §1 bullet mapped to at least one inventory row or explicitly “out of scope” with rationale.
