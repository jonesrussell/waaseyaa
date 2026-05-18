---
work_package_id: WP03
title: Four HTTP controllers
dependencies:
- WP02
requirement_refs:
- FR-002
- FR-003
- FR-007
- FR-009
- FR-010
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T009
- T010
- T011
- T012
- T013
history: []
authoritative_surface: packages/auth/src/Controller/
execution_mode: code_change
owned_files:
- packages/auth/src/Controller/SetupTwoFactorController.php
- packages/auth/src/Controller/EnableTwoFactorController.php
- packages/auth/src/Controller/VerifyTwoFactorController.php
- packages/auth/src/Controller/DisableTwoFactorController.php
- packages/auth/tests/Unit/Controller/SetupTwoFactorControllerTest.php
- packages/auth/tests/Unit/Controller/EnableTwoFactorControllerTest.php
- packages/auth/tests/Unit/Controller/VerifyTwoFactorControllerTest.php
- packages/auth/tests/Unit/Controller/DisableTwoFactorControllerTest.php
tags: []
---

# WP03 — Four HTTP controllers

## Objective

Surface `TwoFactorService` through four POST endpoints. Pattern matches existing `LoginController` exactly: JSON body in, JSON:API envelope out, rate-limited where appropriate.

## Branch strategy

Planning base: `main`. Final merge target: `main`. Execution worktree per `lanes.json`.

## Context

Existing pattern: `packages/auth/src/Controller/LoginController.php`. Use it as the structural template.

JSON:API error envelope:
```json
{
  "jsonapi": {"version": "1.1"},
  "errors": [{"status": "401", "title": "Invalid Code", "detail": "The submitted code is not valid."}]
}
```

Authenticated route option: `_authenticated => true` (set by AuthOidcRouteServiceProvider in WP04).

## Subtasks

### T009 — `SetupTwoFactorController`

POST /auth/2fa/setup. Returns `TwoFactorSetupResult` data. Returns 409 if already enabled.

```php
<?php declare(strict_types=1);
namespace Waaseyaa\Auth\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Auth\TwoFactorService;

/** @api */
final class SetupTwoFactorController
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->attributes->get('_account');
        if ($user === null) {
            return $this->error(401, 'Unauthorized', 'Authentication required.');
        }
        try {
            $result = $this->twoFactor->setup($user);
        } catch (\RuntimeException $e) {
            return $this->error(409, 'Already Enabled', $e->getMessage());
        }
        return new JsonResponse([
            'jsonapi' => ['version' => '1.1'],
            'data' => [
                'type' => 'two-factor-setup',
                'attributes' => [
                    'secret' => $result->secret,
                    'qr_code_uri' => $result->qrCodeUri,
                    'recovery_codes' => $result->recoveryCodes,
                ],
            ],
        ]);
    }

    private function error(int $status, string $title, string $detail): JsonResponse { /* standard JSON:API error envelope */ }
}
```

Test: `packages/auth/tests/Unit/Controller/SetupTwoFactorControllerTest.php`. Cases: happy path returns 200 with all 3 fields; throws-409 when service raises RuntimeException.

### T010 — `EnableTwoFactorController`

POST /auth/2fa/enable. Body: `{secret, recovery_codes, first_code}`. Returns 200 on success, 401 on bad first_code.

```php
public function __invoke(Request $request): JsonResponse
{
    $user = $request->attributes->get('_account');
    if ($user === null) return $this->error(401, 'Unauthorized', 'Authentication required.');
    $body = $this->parseJson($request);
    if ($body === null) return $this->error(400, 'Bad Request', 'Request body is not valid JSON.');
    $secret = (string)($body['secret'] ?? '');
    $codes = (array)($body['recovery_codes'] ?? []);
    $firstCode = (string)($body['first_code'] ?? '');
    if ($secret === '' || $firstCode === '' || $codes === []) {
        return $this->error(400, 'Bad Request', 'secret, recovery_codes, and first_code are required.');
    }
    if (!$this->twoFactor->enable($user, $secret, $codes, $firstCode)) {
        return $this->error(401, 'Invalid Code', 'The submitted code does not match.');
    }
    return new JsonResponse(['jsonapi' => ['version' => '1.1'], 'data' => ['type' => 'two-factor', 'attributes' => ['enabled' => true]]]);
}
```

Test: happy path persists; wrong first_code returns 401 + service.enable returns false (no persistence side effect).

### T011 — `VerifyTwoFactorController`

POST /auth/2fa/verify. Body: `{code}`. Used during login (second factor step) AND for sensitive operations (e.g., disable confirmation).

Rate-limit: 5 attempts per IP per 60s. Use `RateLimiterInterface` injected. Key prefix: `2fa-verify:` (NOT same as `login:` per plan.md).

```php
public function __construct(
    private readonly TwoFactorService $twoFactor,
    private readonly RateLimiterInterface $rateLimiter,
) {}

public function __invoke(Request $request): JsonResponse
{
    $user = $request->attributes->get('_account');
    if ($user === null) return $this->error(401, 'Unauthorized', 'Authentication required.');
    if (!$this->twoFactor->isEnabled($user)) return $this->error(400, 'Two-Factor Not Enabled', 'User has not enabled 2FA.');
    $ip = $request->getClientIp() ?? '127.0.0.1';
    $key = '2fa-verify:' . $ip;
    if ($this->rateLimiter->tooManyAttempts($key, 5)) {
        return $this->error(429, 'Too Many Requests', 'Too many verification attempts. Please try again later.')->headers->set('Retry-After', '60');
    }
    $body = $this->parseJson($request);
    if ($body === null || !isset($body['code']) || !is_string($body['code'])) return $this->error(400, 'Bad Request', 'code is required.');
    if (!$this->twoFactor->verify($user, $body['code'])) {
        $this->rateLimiter->hit($key, 60);
        return $this->error(401, 'Invalid Code', 'The submitted code is not valid.');
    }
    return new JsonResponse(['jsonapi' => ['version' => '1.1'], 'data' => ['type' => 'two-factor', 'attributes' => ['verified' => true]]]);
}
```

Test: happy path returns 200; wrong code returns 401 + rate-limiter.hit called; rate-limited returns 429; not-enabled returns 400.

### T012 — `DisableTwoFactorController`

POST /auth/2fa/disable. Body: `{code}`. Requires a valid TOTP/recovery code as proof.

```php
public function __invoke(Request $request): JsonResponse
{
    $user = $request->attributes->get('_account');
    if ($user === null) return $this->error(401, 'Unauthorized', 'Authentication required.');
    if (!$this->twoFactor->isEnabled($user)) return $this->error(400, 'Two-Factor Not Enabled', 'User has not enabled 2FA.');
    $body = $this->parseJson($request);
    $code = (string)($body['code'] ?? '');
    if ($code === '' || !$this->twoFactor->verify($user, $code)) {
        return $this->error(401, 'Invalid Code', 'Code required to disable two-factor.');
    }
    $this->twoFactor->disable($user);
    return new JsonResponse(['jsonapi' => ['version' => '1.1'], 'data' => ['type' => 'two-factor', 'attributes' => ['enabled' => false]]]);
}
```

Test: happy path wipes credentials; wrong code returns 401 without wiping.

### T013 — Shared response helper (only if duplication emerges)

If `error()` + `parseJson()` end up identical across all four controllers, extract to a trait `TwoFactorResponseTrait` in `packages/auth/src/Controller/`. Otherwise, keep inline. Default decision: KEEP INLINE (avoid premature abstraction per CLAUDE.md guidelines).

## Definition of Done

- Four controller classes exist with `@api` PHPDoc.
- Four corresponding unit tests pass.
- Each controller covers the documented happy + failure modes.
- `composer cs-check`, `composer phpstan` green.

## Risks

- **Risk:** `_account` request attribute key — must be exact match for `SessionMiddleware` per CLAUDE.md gotcha. NOT `account`. *Mitigation:* use the exact existing pattern from `LoginController` if it consumes _account, or from any controller that does.
- **Risk:** RateLimiterInterface DI binding may not be available in test context. *Mitigation:* use a fake in tests (mirror `LoginControllerTest`).
- **Risk:** Symfony imports must be in the allowlist. *Mitigation:* `Request` + `JsonResponse` are already in `.symfony-import-allowlist.json` for the auth package (LoginController uses them).

## Reviewer guidance

- Verify the rate-limit key prefix is `2fa-verify:`, NOT `login:`.
- Verify the verify endpoint hit() is called only on FAILED attempts (not on every call) — otherwise legit users hit the limit.
- Verify the disable endpoint refuses ALL no-code requests (defense in depth).

## Implement command

```bash
spec-kitty agent action implement WP03 --agent <name>
```
