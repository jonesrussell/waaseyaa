---
work_package_id: WP02
title: Foundation HTTP Request type (class_alias per C2)
dependencies:
- WP01
requirement_refs:
- C-002
- C-004
- FR-002
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks: []
assignee: claude
agent: claude
history: []
authoritative_surface: packages/foundation/src/Http/
execution_mode: code_change
owned_files:
- packages/foundation/src/Http/Request.php
- packages/foundation/composer.json
- kitty-specs/1107-api-symfony-decoupling/tasks.md
tags: []
---

# WP02 — Foundation HTTP Request type (class_alias per C2)

## Goal

Publish `Waaseyaa\Foundation\Http\Request` as a Waaseyaa-owned name for
Symfony's `HttpFoundation\Request`, per ratified contract **C2 = (a) class
alias**. Also perform the **C4 layer-rule prerequisite check** for WP04 and
record the chosen shim path in `tasks.md`.

## Context

- Spec: `kitty-specs/1107-api-symfony-decoupling/spec.md`
- Plan: `kitty-specs/1107-api-symfony-decoupling/plan.md` (Phase 1)
- Tasks summary: `kitty-specs/1107-api-symfony-decoupling/tasks.md` (WP02 row)
- `CLAUDE.md` layer rules: Foundation (L0) cannot import API (L4).
- Existing usage: HttpKernel and ControllerDispatcher type-hint
  `Symfony\Component\HttpFoundation\Request` directly today.

## Acceptance Criteria

- **FR-002 / C-002**: `Waaseyaa\Foundation\Http\Request` is resolvable and is
  the **same class** as `Symfony\Component\HttpFoundation\Request` (verified
  by `Request::class === \Symfony\Component\HttpFoundation\Request::class`
  after alias load).
- **No behavior change**: existing HttpKernel routing, middleware pipeline,
  and ControllerDispatcher continue to function with no signature flips.
- **Autoload entry**: `packages/foundation/composer.json` autoloads the alias
  file (e.g., via `autoload.files` or a class entry).
- **C-004 prerequisite check**: WP02 records in `tasks.md` whether the
  foundation→api deprecation shim for `JsonApiResponseTrait` is implementable
  without an L0→L4 import edge. If no clean path exists, surface to user
  before WP04 enters implement; do NOT silently fall back to C4 option (b).
- **Layer enforcement clean**: `bin/check-package-layers` passes.

## Subtasks

- [ ] T002 — Create `packages/foundation/src/Http/Request.php` containing the
  `class_alias(\Symfony\Component\HttpFoundation\Request::class, 'Waaseyaa\\Foundation\\Http\\Request')`
  call (with `if (!class_exists(...))` guard).
- [ ] T003 — Add an autoload entry in `packages/foundation/composer.json`
  (`autoload.files`) so the alias loads on Composer bootstrap. Run
  `composer dump-autoload --optimize` and verify the entry resolves.
- [ ] T004 — Layer-rule prerequisite for C-004: investigate whether
  `JsonApiResponseTrait` can be moved to `packages/api` with a foundation-side
  deprecation shim that does NOT add `Waaseyaa\Api\*` to
  `packages/foundation/composer.json` `require`. Document the chosen shim
  approach in `kitty-specs/1107-api-symfony-decoupling/tasks.md` (WP02
  status note). Acceptable approaches:
    1. `class_alias` shim inside foundation referencing the api class by
       string only (no `use` statement, no `require` edge).
    2. Hard-delete the foundation trait — apps adopting `JsonApiResponse`
       will only ever use the api trait.
    3. Foundation trait re-implements the response payload internally
       without calling api code.
  If none are clean, surface to user.

## Test strategy

- Unit test: a lightweight assertion in
  `packages/foundation/tests/Unit/Http/RequestAliasTest.php` that
  `Request::class === \Symfony\Component\HttpFoundation\Request::class`
  after autoload.
- Existing HttpKernel tests must continue to pass.
- Layer audit: `bin/check-package-layers` reports clean.

## Verification

- `composer dump-autoload --optimize` succeeds.
- `./vendor/bin/phpunit packages/foundation/tests/Unit/Http/RequestAliasTest.php` green.
- `bin/check-package-layers` exits 0.
- `tasks.md` WP02 row updated with the chosen C4 shim approach.

## Definition of Done

- Class alias file shipped, autoloaded, and tested.
- C4 shim approach decided and recorded in `tasks.md`.
- No new L0→L4 dependency edges introduced.
- WP02 lane merged back into main.

## Risks

- **Dump-autoload regression**: if the alias is registered via
  `autoload.classmap`, optimized installs may miss it. Prefer
  `autoload.files`. Severity: low.
- **Shim path unclear**: if C-004 prerequisite surfaces no clean path, WP04
  blocks. Severity: medium. Mitigation: surface to user; do not downgrade.

## Reviewer guidance

- Confirm the alias call is idempotent (`if (!class_exists(...))`).
- Check `composer dump-autoload` was committed (lockfile/classmap unchanged
  is fine if `autoload.files` is the registration vector).
- Confirm `tasks.md` WP02 row records the C4 shim decision.

## Activity Log

- (To be appended by the implementer.)
