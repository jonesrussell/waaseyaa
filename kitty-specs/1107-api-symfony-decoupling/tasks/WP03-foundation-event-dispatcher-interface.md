---
work_package_id: WP03
title: Foundation EventDispatcherInterface + Symfony adapter
dependencies:
- WP01
requirement_refs:
- C-003
- FR-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks: []
assignee: claude
agent: "claude"
history: []
authoritative_surface: packages/foundation/src/Event/
execution_mode: code_change
owned_files:
- packages/foundation/src/Event/EventDispatcherInterface.php
- packages/foundation/src/Event/SymfonyEventDispatcherAdapter.php
- packages/foundation/tests/Contract/EventDispatcherContractTest.php
tags: []
shell_pid: "645614"
---

# WP03 — Foundation EventDispatcherInterface + Symfony adapter

## Goal

Introduce `Waaseyaa\Foundation\Event\EventDispatcherInterface` (PSR-14
compatible: `dispatch(object $event): object`) and a
`SymfonyEventDispatcherAdapter` default binding that wraps Symfony's
dispatcher. Per ratified C3 (a), `DomainEvent` continues to extend
`Symfony\Contracts\EventDispatcher\Event` — only the dispatcher gets a
Waaseyaa-owned interface.

## Acceptance Criteria

- **FR-003 / C-003**: `Waaseyaa\Foundation\Event\EventDispatcherInterface`
  exists with PSR-14 signature.
- `SymfonyEventDispatcherAdapter implements EventDispatcherInterface`,
  delegates to Symfony's `EventDispatcherInterface` for both `dispatch()`
  and listener / subscriber registration.
- Kernel binds `SymfonyEventDispatcherAdapter` as the default implementation
  in the service container.
- Consumer packages migrate type-hints from
  `Symfony\Contracts\EventDispatcher\EventDispatcherInterface` to the
  Waaseyaa interface in **public APIs only** — internal Symfony dispatcher
  use is fine.
- Contract test in `packages/foundation/tests/Contract/` covers:
  - dispatch returns the same event instance
  - listener registration + invocation
  - subscriber registration (Symfony-style)
  - stoppable-event semantics (PSR-14)

## Subtasks

- [ ] T005 — Define `Waaseyaa\Foundation\Event\EventDispatcherInterface` with
  PSR-14 `dispatch(object $event): object` and Symfony-style `addListener` /
  `addSubscriber` / `removeListener` / `removeSubscriber` / `getListeners`.
- [ ] T006 — Implement `SymfonyEventDispatcherAdapter` wrapping
  `Symfony\Component\EventDispatcher\EventDispatcher`. Bind it in the
  Foundation `ServiceProvider` (or kernel bootstrapper) as the default for
  the new interface.
- [ ] T007 — Add `EventDispatcherContractTest` in
  `packages/foundation/tests/Contract/`. Use `#[CoversNothing]` for the
  contract test class. Cover dispatch, listener, subscriber, stoppable
  event paths.

## Test strategy

- Contract test extends an abstract base if useful, OR uses anonymous
  classes for `Event` and listener targets.
- PHPUnit's `createMock()` cannot mock the new interface against an
  intersection type — use real instances.

## Verification

- `./vendor/bin/phpunit packages/foundation/tests/Contract/EventDispatcherContractTest.php` green.
- `bin/check-package-layers` clean (no new upward edges).
- Kernel boot still passes basic smoke (`bin/waaseyaa --help` runs).

## Definition of Done

- Interface and adapter shipped, kernel-bound by default.
- Contract test passes.
- No app-code call sites break (consumer packages migrated where they
  type-hint the dispatcher in public method signatures).

## Risks

- **Adapter correctness**: PSR-14 covers stoppable events but not
  subscriber-discovery — the adapter must preserve Symfony's subscriber
  contract. Mitigation: contract test. Severity: medium.
- **Service-container drift**: if multiple providers bind a dispatcher,
  ordering matters. Mitigation: bind in foundation's provider, document
  override pattern. Severity: low.

## Reviewer guidance

- Confirm interface signature is PSR-14 (`dispatch(object $event): object`).
- Confirm adapter forwards subscriber registration without mutating
  closure binding semantics.
- Confirm consumer packages updated to use the Waaseyaa interface in their
  public method signatures.

## Activity Log

- (To be appended by the implementer.)
- 2026-05-03T15:40:31Z – claude – shell_pid=645614 – Started review via action command
- 2026-05-03T15:40:59Z – claude – shell_pid=645614 – Self-review passed. EventDispatcherContractTest 6/6 green (8 assertions). Foundation 947/947, Unit suite 6388/6388, Integration 782/782 — all green. Public APIs (HasCommandsInterface, HasRenderCacheListenersInterface) migrated to Waaseyaa interface; implementers (NorthCloud, SSR) updated. Adapter implements both Waaseyaa and Symfony component interfaces so internal kernel pipes need no migration. Surface map records new interface as 'public'. bin/check-package-layers, composer check-composer-policy, php-cs-fixer all clean.
