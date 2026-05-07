# Work Packages: Foundation Symfony Fallback Elimination

**Mission ID**: `01KQZR1ELIMSYMFALLBACKPATH01` (mid8: `01KQZR1E`)  
**Mission slug**: `foundation-symfony-fallback-elimination-01KQZR1`  
**Spec**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md) · **Contract**: [contracts/resolution-and-dispatch.md](./contracts/resolution-and-dispatch.md)  
**Branch contract**: `main` → planning base `main` → merge target `main`  
**Generated**: 2026-05-07

---

## Overview

Five work packages. WP01 planning inventory; WP02 contract only (`contracts/`); WP03 merges resolver + Symfony routing exception decoupling (single owner for `HttpKernel` + `WaaseyaaRouter` + resolver); WP04 controller shape; WP05 docs and full gates.

| WP | Title | Subtasks | Depends on | Lane |
|----|-------|----------|------------|------|
| WP01 | Fallback inventory & disposition | 4 | — | planning |
| WP02 | Contract ratification | 2 | WP01 | A |
| WP03 | HTTP resolver + routing outcomes | 6 | WP02 | A |
| WP04 | `_controller` shape contract | 2 | WP03 | A |
| WP05 | Docs, CHANGELOG, gates | 5 | WP04 | A |

---

## Subtask index

| ID | Description | WP |
|----|-------------|-----|
| T001 | Grep + read kernel, resolver, `ProviderRegistryKernelServices`, `ControllerDispatcher`, `WaaseyaaRouter` | WP01 |
| T002 | Write `artifacts/fallback-inventory.md` | WP01 |
| T003 | Grep SSR for `HttpServiceResolverInterface` / `getHttpServiceResolver` | WP01 |
| T004 | Note layer constraints from `bin/check-package-layers` for routing ↔ foundation | WP01 |
| T005 | Mark contract sections approved/revised | WP02 |
| T006 | Finalize `contracts/resolution-and-dispatch.md` | WP02 |
| T008 | Implement resolver delegation + `HttpKernel` wiring | WP03 |
| T009 | Extend `HttpKernelServiceResolverTest` | WP03 |
| T010 | PHPStan for touched foundation/routing files | WP03 |
| T011 | Implement routing miss / 405 outcome per contract | WP03 |
| T012 | Remove Symfony exception catches from `HttpKernel` | WP03 |
| T013 | Update `HttpKernelTest` + `WaaseyaaRouterTest` (+ new tests if added) | WP03 |
| T014 | Controller / `RouteBuilder` boundary per contract | WP04 |
| T015 | `ControllerDispatcherTest` + `RouteBuilderTest` | WP04 |
| T016 | Conditional `packages/ssr` PHPUnit if WP03–WP04 touched SSR | WP05 |
| T017 | CHANGELOG `[Unreleased]` | WP05 |
| T018 | `docs/specs/infrastructure.md` sync | WP05 |
| T019 | `./vendor/bin/phpunit`, `composer phpstan`, `composer cs-check` | WP05 |
| T020 | `scripts/check-lifecycle-drift.sh` + lifecycle doc if needed | WP05 |

---

## WP01 — Fallback inventory & disposition

**Goal**: `artifacts/fallback-inventory.md` is the scope authority.

**Included subtasks**: T001–T004

**Owned files**: `kitty-specs/foundation-symfony-fallback-elimination-01KQZR1/artifacts/**`

**Requirement refs**: FR-001, FR-002, FR-003, C-001–C-003

**Prompt**: `tasks/WP01-fallback-inventory.md`

---

## WP02 — Contract ratification

**Goal**: Approved `contracts/resolution-and-dispatch.md` (no `infrastructure.md` edits — deferred to WP05).

**Dependencies**: WP01

**Included subtasks**: T005–T006

**Owned files**: `kitty-specs/foundation-symfony-fallback-elimination-01KQZR1/contracts/**`

**Requirement refs**: FR-004, SC-004

**Prompt**: `tasks/WP02-contract-ratification.md`

---

## WP03 — HTTP resolver + routing outcomes

**Goal**: SC-001 + SC-002 in one ownership boundary for `HttpKernel`, `HttpKernelServiceResolver`, `WaaseyaaRouter`.

**Dependencies**: WP02

**Included subtasks**: T008–T013

**Owned files**: `packages/foundation/src/Kernel/HttpKernel.php`, `packages/foundation/src/Kernel/Http/HttpKernelServiceResolver.php`, `packages/routing/src/WaaseyaaRouter.php`, `packages/foundation/tests/Unit/Kernel/HttpKernelTest.php`, `packages/foundation/tests/Unit/Kernel/Http/HttpKernelServiceResolverTest.php`, `packages/routing/tests/Unit/WaaseyaaRouterTest.php`

**Requirement refs**: FR-001, FR-002, SC-001, SC-002, NFR-002, C-002

**Prompt**: `tasks/WP03-http-kernel-resolver-and-routing.md`

---

## WP04 — `_controller` shape contract

**Goal**: SC-003.

**Dependencies**: WP03

**Included subtasks**: T014–T015

**Owned files**: `packages/foundation/src/Http/ControllerDispatcher.php`, `packages/routing/src/RouteBuilder.php`, `packages/foundation/tests/Unit/Http/ControllerDispatcherTest.php`, `packages/routing/tests/Unit/RouteBuilderTest.php`

**Requirement refs**: FR-003, SC-003, C-002

**Prompt**: `tasks/WP04-controller-shape.md`

---

## WP05 — Docs, changelog, gates

**Goal**: SC-004, SC-005.

**Dependencies**: WP04

**Included subtasks**: T016–T020

**Owned files**: `CHANGELOG.md`, `docs/specs/infrastructure.md`

**Requirement refs**: FR-004, FR-005, SC-004, SC-005

**Prompt**: `tasks/WP05-docs-and-gates.md`
