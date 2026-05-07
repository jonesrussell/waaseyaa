---
work_package_id: WP04
title: Controller _controller shape contract
dependencies:
- WP03
requirement_refs:
- FR-003
- C-002
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T014
- T015
history: []
authoritative_surface: packages/foundation/src/Http/
execution_mode: code_change
mission_id: 01KQZR1ELIMSYMFALLBACKPATH01
mission_slug: foundation-symfony-fallback-elimination-01KQZR1
owned_files:
- packages/foundation/src/Http/ControllerDispatcher.php
- packages/routing/src/RouteBuilder.php
- packages/foundation/tests/Unit/Http/ControllerDispatcherTest.php
- packages/routing/tests/Unit/RouteBuilderTest.php
tags: []
---

# WP04 — `_controller` shape contract

## Objective

**SC-003**: Single normalization locus per WP02 contract — `ControllerDispatcher` consumes the agreed shape; `RouteBuilder` (or chosen registration path) owns any `[FQCN, method]` normalization or deprecation.

## Subtasks

### T014 — Implement boundary change

Apply contract: remove or relocate array normalization from `ControllerDispatcher`; update `RouteBuilder` if routes must emit string controllers.

### T015 — Tests

Update `ControllerDispatcherTest` and `RouteBuilderTest` for the new contract.

## Validation

- [ ] No undocumented dual normalization.
- [ ] PHPUnit green for the four owned files’ test scope.
