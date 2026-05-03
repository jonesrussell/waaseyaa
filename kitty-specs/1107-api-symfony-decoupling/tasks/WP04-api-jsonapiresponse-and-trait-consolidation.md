---
work_package_id: WP04
title: JsonApiResponse + trait consolidation in api package
dependencies:
- WP02
requirement_refs:
- C-001
- C-004
- FR-001
- FR-004
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks: []
assignee: claude
agent: claude
history: []
authoritative_surface: packages/api/src/Http/
execution_mode: code_change
owned_files:
- packages/api/src/Http/JsonApiResponse.php
- packages/api/src/JsonApiResponseTrait.php
- packages/api/src/Controller/JsonApiController.php
- packages/api/src/Controller/SchemaController.php
- packages/foundation/src/Http/JsonApiResponseTrait.php
tags: []
---

# WP04 — JsonApiResponse + trait consolidation

## Goal

Ship `Waaseyaa\Api\Http\JsonApiResponse` (subclass of Symfony's `JsonResponse`
per ratified C1 (a)). Move the canonical JSON:API response trait from
foundation to `packages/api`. Migrate `JsonApiController` and
`SchemaController` to use the new response type. Apply the C-004 shim
approach recorded by WP02.

## Acceptance Criteria

- **FR-001 / C-001**:
  `Waaseyaa\Api\Http\JsonApiResponse extends \Symfony\Component\HttpFoundation\JsonResponse`.
  `instanceof Response` continues to work in `ControllerDispatcher`. No
  translation layer.
- **FR-004 / C-004**: Canonical JSON:API response trait lives in
  `packages/api`. Foundation's previous trait is replaced per the shim
  approach WP02 recorded in `tasks.md` (alias-by-string, hard-delete, or
  internal re-implementation). No new L0→L4 import edge.
- `JsonApiController` and `SchemaController` migrated to construct and
  return `JsonApiResponse` instances.
- `Waaseyaa\Api\JsonResponseTrait` (the duplicate name in api) is removed.
- All existing API tests continue to pass.

## Subtasks

- [ ] T008 — Create `Waaseyaa\Api\Http\JsonApiResponse` extending
  `\Symfony\Component\HttpFoundation\JsonResponse`. Constructor signature
  matches the existing trait's response shape (data, status, headers).
- [ ] T009 — Move the canonical trait to `packages/api/src/JsonApiResponseTrait.php`
  (or whatever name the audit shows is canonical). Update all api-package
  consumers.
- [ ] T010 — Apply the C-004 shim path recorded by WP02 in `tasks.md` to
  `packages/foundation/src/Http/JsonApiResponseTrait.php`. Run
  `bin/check-package-layers` to confirm no new upward edge.
- [ ] T011 — Migrate `JsonApiController::index/show/store/update/delete`
  and `SchemaController::show` to return `JsonApiResponse`. Remove
  `Waaseyaa\Api\JsonResponseTrait` once unused.

## Test strategy

- Existing `JsonApiController` and `SchemaController` integration tests
  must continue to pass. If they assert on
  `\Symfony\Component\HttpFoundation\JsonResponse`, switch to
  `JsonApiResponse` (or the abstract `Response`).
- Add a unit test that constructs `JsonApiResponse` and asserts:
  - `instanceof JsonResponse` (parent class)
  - `instanceof Response` (grandparent)
  - JSON encode round-trip preserves data shape.

## Verification

- `./vendor/bin/phpunit --testsuite Unit` green.
- `./vendor/bin/phpunit --testsuite Integration` green.
- `bin/check-package-layers` clean.
- `bin/audit-require-dev-layers` does not gain new upward edges.

## Definition of Done

- New response type shipped and used by both controllers.
- Old trait names removed.
- Layer audit clean.
- WP04 lane merged back into main.

## Risks

- **C-004 shim divergence**: if WP02 surfaced no clean path and user
  decided differently, this WP must follow the user's decision, not
  silently fall back.
- **Subclass leak**: app developers can type-hint `JsonResponse` and miss
  the abstraction. Mitigation: WP05 contract test; deferred linter (C-005
  follow-up) closes the gap longer-term. Severity: medium.

## Reviewer guidance

- Confirm `JsonApiResponse` extends, not wraps, Symfony's `JsonResponse`.
- Confirm the shim path matches what WP02 recorded in `tasks.md`.
- Confirm `JsonApiController` and `SchemaController` no longer construct
  `JsonResponse` directly.

## Activity Log

- (To be appended by the implementer.)
