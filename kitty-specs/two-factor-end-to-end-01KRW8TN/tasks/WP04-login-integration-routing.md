---
work_package_id: WP04
title: LoginController integration + routes + DI
dependencies:
- WP02
- WP03
requirement_refs:
- FR-006
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T014
- T015
- T016
- T017
history: []
authoritative_surface: packages/auth/src/
execution_mode: code_change
owned_files:
- packages/auth/src/Controller/LoginController.php
- packages/auth/tests/Unit/Controller/LoginControllerTest.php
- packages/auth/src/AuthServiceProvider.php
- packages/routing/src/AuthOidcRouteServiceProvider.php
tags: []
---

# WP04 — LoginController integration + routes + DI

## Objective

Wire the new controllers into the routing layer, bind `TwoFactorService` in DI, and modify `LoginController` to emit `state: 2fa_required` when 2FA is on.

## Branch strategy

Planning base: `main`. Final merge target: `main`. Execution worktree per `lanes.json`.

## Context

Routes for `waaseyaa/auth` live in `packages/routing/src/AuthOidcRouteServiceProvider.php` (per CLAUDE.md L1↔L4 boundary rule). Existing controllers wire via `new XxxController(...)` inline; we follow that.

`LoginController` already injects `EntityTypeManager` and `RateLimiterInterface`. Adding `TwoFactorService` is the same layer.

## Subtasks

### T014 — Modify LoginController

1. Add `TwoFactorService` to constructor:
   ```php
   public function __construct(
       private readonly EntityTypeManager $entityTypeManager,
       private readonly RateLimiterInterface $rateLimiter,
       private readonly TwoFactorService $twoFactor,
   ) {}
   ```

2. After password verifies (find the existing success-path code that issues the session), branch:
   ```php
   if ($this->twoFactor->isEnabled($user)) {
       return new JsonResponse([
           'jsonapi' => ['version' => '1.1'],
           'data' => [
               'type' => 'auth',
               'attributes' => [
                   'state' => '2fa_required',
                   'pending_user_id' => $user->id(),
               ],
           ],
       ]);
   }
   // existing session issuance path
   ```

3. The existing test must still pass; this only adds a new branch for the 2FA case.

### T015 — Update LoginControllerTest

Add a test method `testLoginWith2faEnabledReturns2faRequired`:
- Set up a User with `two_factor_secret` populated.
- POST username/password.
- Assert response status 200, body contains `attributes.state == "2fa_required"` and `attributes.pending_user_id == <user_id>`.
- Assert response does NOT include a session token (or whatever key the existing happy path returns).

### T016 — Register 4 routes in `AuthOidcRouteServiceProvider`

Add (mirroring the existing route pattern):

```php
$router->route('POST', '/auth/2fa/setup')
    ->controller(new SetupTwoFactorController($this->get(TwoFactorService::class)))
    ->options(['_authenticated' => true]);
$router->route('POST', '/auth/2fa/enable')
    ->controller(new EnableTwoFactorController($this->get(TwoFactorService::class)))
    ->options(['_authenticated' => true]);
$router->route('POST', '/auth/2fa/verify')
    ->controller(new VerifyTwoFactorController(
        $this->get(TwoFactorService::class),
        $this->get(RateLimiterInterface::class),
    ))
    ->options(['_authenticated' => true]);
$router->route('POST', '/auth/2fa/disable')
    ->controller(new DisableTwoFactorController($this->get(TwoFactorService::class)))
    ->options(['_authenticated' => true]);
```

Also: the existing `new LoginController(...)` line gains a third argument `$this->get(TwoFactorService::class)`.

### T017 — Bind `TwoFactorService` in `AuthServiceProvider`

```php
$this->singleton(TwoFactorService::class, fn () => new TwoFactorService(
    $this->get(TwoFactorManager::class),
    $this->get(EntityTypeManager::class),
));
```

Add after the existing `TwoFactorManager` binding.

## Definition of Done

- LoginController branches on `TwoFactorService::isEnabled($user)`.
- Four new routes registered.
- DI binding for `TwoFactorService` in place.
- All existing tests still pass.
- New test method `testLoginWith2faEnabledReturns2faRequired` passes.

## Risks

- **Risk:** Existing client behavior breaks if it doesn't expect `state` attribute. *Mitigation:* clients without 2FA-enabled users see no change.
- **Risk:** RouteProvider gets bigger; controller-string detection (#1500 fix) handles it.

## Reviewer guidance

- Verify routes have `_authenticated => true` option set (auth middleware must enforce).
- Verify LoginController returns `state: 2fa_required` BEFORE issuing a session token.
- Run `bin/check-symfony-imports` to confirm no new violations.

## Implement command

```bash
spec-kitty agent action implement WP04 --agent <name>
```
