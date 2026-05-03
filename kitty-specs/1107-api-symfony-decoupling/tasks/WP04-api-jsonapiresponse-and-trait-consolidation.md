---
work_package_id: WP04
title: "JsonApiResponse + trait consolidation (foundation-canonical per amended C-004)"
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
agent: "claude"
history: []
authoritative_surface: packages/api/src/Http/
execution_mode: code_change
owned_files:
- packages/api/src/Http/JsonApiResponse.php
- packages/api/src/JsonResponseTrait.php
- packages/api/src/Controller/JsonApiController.php
- packages/api/src/Controller/SchemaController.php
- packages/foundation/src/Http/JsonApiResponseTrait.php
tags: []
shell_pid: "647960"
---

# WP04 — JsonApiResponse + trait consolidation (foundation-canonical)

> **C-004 amended on 2026-05-03 to (a-inverted).** WP02's audit found the original "canonical trait moves to api" plan unimplementable — PHP traits cannot be `class_alias`'d, and foundation has 9 internal consumers (`HttpKernel`, `ControllerDispatcher`, 7 routers) that cannot be unwound without a much larger refactor. The amended plan keeps canonical in foundation and deletes api's duplicate. See `spec.md` C4 section and `tasks/WP02-foundation-http-request-type.md` activity log for the audit evidence.

## Goal

Ship `Waaseyaa\Api\Http\JsonApiResponse` (subclass of Symfony's `JsonResponse` per ratified C-001). Delete the duplicate `Waaseyaa\Api\JsonResponseTrait` in the api package and migrate api consumers to import the canonical `Waaseyaa\Foundation\Http\JsonApiResponseTrait` directly. `JsonApiController` and `SchemaController` migrate to construct and return `JsonApiResponse` instances.

## Acceptance Criteria

- **FR-001 / C-001**:
  `Waaseyaa\Api\Http\JsonApiResponse extends \Symfony\Component\HttpFoundation\JsonResponse`.
  `instanceof Response` continues to work in `ControllerDispatcher`. No
  translation layer.
- **FR-004 / amended C-004**: Canonical JSON:API response trait stays at
  `Waaseyaa\Foundation\Http\JsonApiResponseTrait` (untouched). The duplicate
  `Waaseyaa\Api\JsonResponseTrait` (in `packages/api/src/JsonResponseTrait.php`)
  is **deleted**. Any api code that referenced it now `use`s foundation's
  trait directly (L4 → L0 import is allowed by the layer rule).
- `JsonApiController` and `SchemaController` migrated to construct and
  return `JsonApiResponse` instances.
- All existing API tests continue to pass.
- `bin/check-package-layers` clean.

## Subtasks

- [ ] T008 — Create `Waaseyaa\Api\Http\JsonApiResponse` extending
  `\Symfony\Component\HttpFoundation\JsonResponse`. Constructor signature
  matches the existing trait's response shape (data, status, headers,
  encoding flags `JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR`,
  Content-Type `application/vnd.api+json`).
- [ ] T009 — Identify every api-package consumer of `Waaseyaa\Api\JsonResponseTrait`
  (`grep -r "use Waaseyaa\\\\Api\\\\JsonResponseTrait"` then case-fold variants).
  Switch each to `use Waaseyaa\Foundation\Http\JsonApiResponseTrait`. Confirm
  the trait's `jsonApiResponse(int $status, array $data, array $headers = [])`
  signature is what the consumers call.
- [ ] T010 — Delete `packages/api/src/JsonResponseTrait.php`. Confirm via
  grep that no live references remain (excluding mission docs). Run
  `bin/check-package-layers`.
- [ ] T011 — Migrate `JsonApiController::index/show/store/update/delete` and
  `SchemaController::show` to return `JsonApiResponse` instead of
  `JsonResponse`. Existing `JsonApiResponseTrait::jsonApiResponse()` returns
  the new class (or call `JsonApiResponse` constructor directly inside the
  trait). If trait return type tightens to `JsonApiResponse`, audit any
  caller asserting against the parent class.

## Note on foundation's trait

WP04 must NOT modify `Waaseyaa\Foundation\Http\JsonApiResponseTrait` itself — its 9 in-package consumers and 4 cross-layer consumers expect the existing signature. If the trait's `jsonApiResponse()` should *return* the new `JsonApiResponse` class instead of Symfony's `JsonResponse`, that change is acceptable but must be a downward dependency (foundation cannot import `Waaseyaa\Api\Http\JsonApiResponse` — L0 cannot import L4). The simplest path: leave foundation's trait returning Symfony's `JsonResponse`; api consumers wrap or override as needed. WP04 evaluates and records the chosen tradeoff in this WP's activity log.

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

- New `Waaseyaa\Api\Http\JsonApiResponse` class shipped and returned by both controllers.
- `packages/api/src/JsonResponseTrait.php` deleted; api consumers import foundation's canonical trait.
- `packages/foundation/src/Http/JsonApiResponseTrait.php` left intact (its 9+4 consumers continue to work).
- Layer audit clean (`bin/check-package-layers`).
- WP04 lane merged back into main.

## Risks

- **Subclass leak**: app developers can type-hint Symfony's `JsonResponse` and miss
  the new `JsonApiResponse` abstraction. Mitigation: WP05 contract test; deferred
  linter (C-005 follow-up) closes the gap longer-term. Severity: medium.
- **Foundation trait return type**: if WP04 needs foundation's trait to return
  `JsonApiResponse`, that creates an L0→L4 import which is forbidden. Foundation
  must continue returning Symfony's `JsonResponse`; api wraps/overrides as needed.
  Severity: low if disciplined; high if accidentally introduced.

## Reviewer guidance

- Confirm `JsonApiResponse` extends, not wraps, Symfony's `JsonResponse`.
- Confirm `packages/api/src/JsonResponseTrait.php` is gone and grep finds no live references to `Waaseyaa\Api\JsonResponseTrait`.
- Confirm `packages/foundation/src/Http/JsonApiResponseTrait.php` is unchanged (its existing consumers must keep working).
- Confirm `JsonApiController` and `SchemaController` return `JsonApiResponse` instances.
- Confirm no L0→L4 import edge was introduced (i.e. foundation does NOT import `Waaseyaa\Api\*`).

## Activity Log

- (To be appended by the implementer.)
- 2026-05-03T15:48:24Z – claude – shell_pid=647960 – Started review via action command
