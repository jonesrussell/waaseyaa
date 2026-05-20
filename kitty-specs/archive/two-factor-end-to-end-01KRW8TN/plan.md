# Implementation Plan: Two-Factor Authentication End-to-End

**Mission:** `two-factor-end-to-end-01KRW8TN`
**Spec:** [spec.md](./spec.md)
**Target branch:** `main` (planning base = main, merge target = main, matches: true)

## Technical Context

- **Language:** PHP 8.5+
- **Framework:** Waaseyaa (entity-first, attribute-discovered)
- **Layer placement:** All new code lives at L1 (auth, user) or L4 (routing). No upward imports.
- **Storage strategy:** User entity gains two new `#[Field]`-annotated public properties (`two_factor_secret`, `two_factor_recovery_codes_hash`). These persist via the `_data` JSON blob mechanism documented in CLAUDE.md — **no schema migration required**. Existing User rows continue to load cleanly (missing fields default to `null` / `[]`).
- **Encryption-at-rest:** Deferred to follow-up (per spec Assumptions). Secrets stored as Base32 plaintext in `_data` with a TODO marker comment.
- **HTTP request shape:** JSON in, JSON out, matching the existing JSON:API envelope used by `LoginController`.
- **Auth gate:** All four new routes require an authenticated session (`_account` resolves to non-anonymous User). Already enforced by `SessionMiddleware` + route option `_authenticated`.
- **Rate limiting:** Reuses existing `RateLimiterInterface` (5 attempts per IP per 60s, identical to login).

## Architectural fit

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  HTTP layer (L4)                                                            │
│                                                                             │
│   POST /auth/2fa/setup     ──→ SetupTwoFactorController                     │
│   POST /auth/2fa/enable    ──→ EnableTwoFactorController                    │
│   POST /auth/2fa/verify    ──→ VerifyTwoFactorController                    │
│   POST /auth/2fa/disable   ──→ DisableTwoFactorController                   │
│                                                                             │
│   POST /auth/login (existing)                                               │
│        ├─ verify password                                                   │
│        └─ if TwoFactorService::isEnabled($user) → return state:2fa_required │
└──────────────────────┬──────────────────────────────────────────────────────┘
                       │ uses
                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  Service layer (L1 — packages/auth/src/)                                    │
│                                                                             │
│   TwoFactorService                                                          │
│     • setup(User): TwoFactorSetupResult                                     │
│     • enable(User, secret, recoveryCodes, firstCode): bool                  │
│     • verify(User, code): bool          (TOTP first, recovery fallback)     │
│     • disable(User): void               (atomic wipe)                       │
│     • isEnabled(User): bool                                                 │
└──────────────────────┬──────────────────────────────────────────────────────┘
                       │ composes
                       ▼
┌──────────────────────────────────────┐    ┌────────────────────────────────┐
│  TwoFactorManager (existing)         │    │  EntityTypeManager (existing)  │
│   • generateSecret                   │    │   • getStorage('user')         │
│   • verifyCode                       │    │     → save/load User           │
│   • getCurrentCode                   │    └────────────────────────────────┘
│   • getQrCodeUri                     │
│   • generateRecoveryCodes            │
│   • verifyRecoveryCode               │
└──────────────────────────────────────┘
```

## DI + routing plan

### DI bindings (`packages/auth/src/AuthServiceProvider.php`)

Add one binding:

```php
$this->singleton(TwoFactorService::class, fn () => new TwoFactorService(
    $this->get(TwoFactorManager::class),
    $this->get(EntityTypeManager::class),
));
```

`TwoFactorManager` binding is already in place. `EntityTypeManager` is bound at framework boot.

### Routes (`packages/routing/src/AuthOidcRouteServiceProvider.php`)

Add four routes following the existing pattern (string controller refs like `LoginController` does):

```php
$router->route('POST', '/auth/2fa/setup')
    ->controller(new SetupTwoFactorController(
        $this->get(TwoFactorService::class),
        $this->get(EntityTypeManager::class),
    ))
    ->options(['_authenticated' => true]);
// + /enable, /verify, /disable
```

Note: existing pattern uses `new XxxController(...)` inline; we follow it for consistency.

### LoginController modification

Add `TwoFactorService` as a constructor dependency. After password verifies, before issuing session:

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
    ], 200);
}
// existing session-issuance path continues
```

`VerifyTwoFactorController` accepts `pending_user_id` + `code`, verifies via `TwoFactorService::verify()`, issues the session if successful.

**Backward compat:** Users without 2FA enabled (`isEnabled` returns false) see no behavior change.

## File-by-file diff outline

| Path | Action | Purpose |
|---|---|---|
| `packages/user/src/User.php` | edit | Add two `#[Field]` properties + setters |
| `packages/auth/src/TwoFactorService.php` | create | L1 service composing primitives |
| `packages/auth/src/TwoFactorSetupResult.php` | create | Value object: secret + QR URI + recovery codes |
| `packages/auth/src/Controller/SetupTwoFactorController.php` | create | POST /auth/2fa/setup |
| `packages/auth/src/Controller/EnableTwoFactorController.php` | create | POST /auth/2fa/enable |
| `packages/auth/src/Controller/VerifyTwoFactorController.php` | create | POST /auth/2fa/verify |
| `packages/auth/src/Controller/DisableTwoFactorController.php` | create | POST /auth/2fa/disable |
| `packages/auth/src/Controller/LoginController.php` | edit | Branch to 2fa_required when applicable |
| `packages/auth/src/AuthServiceProvider.php` | edit | Bind TwoFactorService |
| `packages/routing/src/AuthOidcRouteServiceProvider.php` | edit | Register 4 routes + injected TwoFactorService into LoginController |
| `packages/auth/tests/Unit/TwoFactorServiceTest.php` | create | Unit tests for service |
| `packages/auth/tests/Unit/Controller/SetupTwoFactorControllerTest.php` | create | Unit |
| `packages/auth/tests/Unit/Controller/EnableTwoFactorControllerTest.php` | create | Unit |
| `packages/auth/tests/Unit/Controller/VerifyTwoFactorControllerTest.php` | create | Unit |
| `packages/auth/tests/Unit/Controller/DisableTwoFactorControllerTest.php` | create | Unit |
| `tests/Integration/PhaseTwoFactor/TwoFactorE2ETest.php` | create | E2E happy path + recovery + disable |
| `docs/specs/access-control.md` | edit | Add Two-Factor Authentication section |
| `docs/specs/two-factor-auth.md` | create | Full contract for the 4 controllers + service |
| `phpstan-dead-code-baseline.neon` | edit (auto-regen) | 6 TwoFactorManager entries dropped |
| `CHANGELOG.md` | edit | Unreleased entry |

## Recovery code hashing

Hash strategy: `password_hash($code, PASSWORD_ARGON2ID)`. Argon2id is the framework convention (used by `User::setRawPassword`). Per-code hash (8 entries) is small enough that the 100ms NFR holds.

Verification: iterate stored hashes, call `password_verify($input, $hash)`. On match, remove that hash from the user's stored list and persist.

## Edge case handling (matrix)

| Scenario | Controller | Status | Body |
|---|---|---|---|
| Setup when already enabled | SetupTwoFactor | 409 Conflict | `{ errors: [{ status: 409, title: "Already Enabled" }] }` |
| Enable with wrong first code | EnableTwoFactor | 401 | `{ errors: [{ status: 401, title: "Invalid Code" }] }` |
| Verify with no 2FA enabled | VerifyTwoFactor | 400 | `{ errors: [{ status: 400, title: "Two-Factor Not Enabled" }] }` |
| Verify with consumed recovery code | VerifyTwoFactor | 401 | same as wrong code |
| Disable without proof | DisableTwoFactor | 401 | requires code |
| Rate-limited | any verify | 429 | `Retry-After: 60` |
| Missing JSON body | any | 400 | `{ errors: [{ status: 400, title: "Bad Request" }] }` |

## Test plan

### Unit

- `TwoFactorServiceTest` covers all 5 service methods with a fake `EntityTypeManager` returning an in-memory User.
- Each controller test (4) covers happy path + the one or two failure modes specific to that controller. Reuses the rate-limit pattern from `LoginControllerTest`.

### Integration (`tests/Integration/PhaseTwoFactor/TwoFactorE2ETest.php`)

Three test methods:
1. `testTotpFlow`: register → login → enable 2FA → log out → log in → expect 2fa_required → POST /verify with current TOTP → expect session token.
2. `testRecoveryFlow`: register → enable 2FA → log out → log in → POST /verify with recovery code → success → re-attempt same recovery code → 401.
3. `testDisableFlow`: enable → POST /disable with valid code → subsequent login skips 2FA.

Each test boots a full kernel via the standard `WaaseyaaTestCase` base with in-memory SQLite + an in-memory User.

### Verification gates

- `composer cs-check` — clean
- `composer phpstan` — clean
- `composer check-dead-code` — TwoFactorManager entries dropped (6 → 0)
- `composer test` — all phpunit green
- `composer verify` — full chain green

## WP ordering (for /spec-kitty.tasks)

1. **WP01 — User entity fields** (smallest, no dependencies). Add 2 properties + 2 setters. Unit test on User to confirm round-trip via `_data` blob.
2. **WP02 — TwoFactorService + value object**. Depends on WP01. Unit tests with fake EntityTypeManager.
3. **WP03 — Four HTTP controllers**. Depend on WP02. Each with happy-path + error-path unit tests.
4. **WP04 — LoginController integration + routes + DI**. Depends on WP02+WP03. Spans `AuthServiceProvider`, `AuthOidcRouteServiceProvider`, `LoginController`.
5. **WP05 — Integration E2E tests**. Depends on WP04 (full HTTP pipeline must work). Three test methods in `TwoFactorE2ETest`.
6. **WP06 — Wrap-up**: spec edits (`docs/specs/access-control.md` + new `docs/specs/two-factor-auth.md`), CHANGELOG entry, baseline regen + commit, verify all 6 TwoFactorManager entries are gone.

Lane assignment: all WPs on a single lane (sequential dependencies). Could parallelize WP02 + WP03 only if WP03 mocked TwoFactorService — not worth the complexity.

## Gate evaluation

### Layer architecture
- `User` (L1) gains fields — no new imports.
- `TwoFactorService` (L1) imports from L1 only (`TwoFactorManager`, `EntityTypeManager`, `User`).
- New controllers live in `packages/auth/` (L1).
- Route registration in `packages/routing/` (L4) imports controllers — that's a downward import, fine.
- `LoginController` already lives at L1 and already imports `EntityTypeManager` and `RateLimiterInterface`. Adding `TwoFactorService` is the same layer.

### Dead-code constraint
All 6 currently-baselined methods on `TwoFactorManager` are reachable:
- `generateSecret` ← TwoFactorService::setup
- `verifyCode` ← TwoFactorService::verify (TOTP path) + EnableTwoFactorController (first-code proof)
- `getCurrentCode` ← VerifyTwoFactorController DEBUG path? No — drop unless used in tests. **Risk:** `getCurrentCode` may not get a production caller. Resolution: it's tagged as a documented helper; if no caller, we'll annotate `@api` on the class (already true) which already covers it via shipmonk. Confirm in WP06 baseline regen.
- `getQrCodeUri` ← TwoFactorService::setup (built into TwoFactorSetupResult)
- `generateRecoveryCodes` ← TwoFactorService::setup
- `verifyRecoveryCode` ← TwoFactorService::verify (recovery fallback)

### Composer policy
No new packages added. Layer enforcement unchanged.

### Symfony imports
Controllers use `Symfony\Component\HttpFoundation\Request` + `JsonResponse` (already used by LoginController, in allowlist).

## Risk + mitigation

| Risk | Mitigation |
|---|---|
| User entity field addition silently breaks existing User consumers when `_data` JSON missing the keys | Default values in property declarations (`= null`, `= []`) handle absent keys |
| Argon2id hash cost makes 8-code verification slow | Bounded — verify loop short-circuits on first match; 8 iterations × ~10ms = under 100ms p95 |
| LoginController behavior change breaks downstream consumers reading the response shape | Only changes shape for users with 2FA enabled; new field `state` is additive; existing clients ignore unknown fields by JSON:API convention |
| Rate limiter on verify endpoint shares the login key namespace | Use distinct prefix `2fa-verify:` to avoid double-counting |
| getCurrentCode method not reachable from production code | Acceptable — it's documented as a test/debug helper. Class-level `@api` already keeps the baseline clean. |

## Next step

`/spec-kitty.tasks` will materialize the six WPs into `kitty-specs/two-factor-end-to-end-01KRW8TN/tasks/` directory.

Final branch contract: started on `main`, planning base `main`, merge target `main`, branch_matches_target=true.
