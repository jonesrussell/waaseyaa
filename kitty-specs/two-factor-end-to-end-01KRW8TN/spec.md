# Two-Factor Authentication End-to-End

**Mission:** `two-factor-end-to-end-01KRW8TN`
**Status:** Spec
**Target branch:** `main`
**Closes:** #1499

## Why this mission exists

`packages/auth/src/TwoFactorManager` ships TOTP and recovery-code primitives (RFC 6238 + 8-code generation, verification, QR URI) but is wired into nothing. All six of its public methods sit in `phpstan-dead-code-baseline.neon` as unused. A consumer-facing 2FA flow exists in the framework's *parts*, not as a feature any application can adopt.

The framework's stated contract is "no vaporware." Either delete TwoFactorManager and remove the primitives, or finish the wiring so a real consumer (Minoo, the admin SPA, any downstream Waaseyaa app) can call a single endpoint and turn on 2FA for a user. The owner's call is: **finish the wiring.**

## User scenarios

### Primary flow: a user enables 2FA, then logs in with it

1. Authenticated user posts to `/auth/2fa/setup`. Receives back: a Base32 secret, an `otpauth://` QR URI for the authenticator app, and 8 recovery codes (plaintext, one-time display). Nothing is persisted yet.
2. User scans the QR code in an authenticator app, then submits the secret + first 6-digit code to `/auth/2fa/enable`. Server verifies the code; on success, the secret is persisted and the recovery codes are stored hashed.
3. User logs out. On next `POST /auth/login`, after username/password verifies, the response is `{ data: { type: auth, attributes: { state: "2fa_required" } } }` instead of a session token.
4. User submits the current TOTP to `/auth/2fa/verify`. Server returns a normal session token. Login is complete.

### Recovery flow: lost the authenticator device

1. Steps 1-3 of the primary flow.
2. User submits one of the 8 stored recovery codes to `/auth/2fa/verify` instead of a TOTP.
3. Server verifies the recovery code, consumes it (removed from the user's list), and returns a session token.
4. Subsequent attempts with the same recovery code fail. User has 7 remaining.

### Disable flow

1. Authenticated user posts to `/auth/2fa/disable` (with a valid TOTP or recovery code to confirm intent).
2. Server wipes the secret + remaining recovery codes atomically.
3. Subsequent logins skip the second-factor step.

### Edge cases

- Setup called when 2FA already enabled: returns 409 Conflict; user must disable first.
- Enable called with stale/incorrect first code: returns 401, secret NOT persisted, recovery codes NOT persisted.
- Verify called with no 2FA enabled: returns 400.
- Verify called with consumed recovery code: returns 401.
- Concurrent enable requests (rare): last-write-wins on the secret, but recovery codes are merged ⇒ refuse, return 409.
- Rate limiting: 5 failed verify attempts in 60s blocks further attempts (existing `RateLimiterInterface` pattern from `LoginController`).

## Requirements

### Functional

| ID | Status | Requirement |
|---|---|---|
| FR-001 | Mandatory | A `User` record can carry an opaque TOTP secret and a list of hashed recovery codes, or carry neither. There is no intermediate state. |
| FR-002 | Mandatory | The system can generate a fresh TOTP secret + 8 recovery codes for any authenticated user via a single HTTP call. |
| FR-003 | Mandatory | A user proves possession of the secret by submitting a valid TOTP-derived code BEFORE the secret is persisted. |
| FR-004 | Mandatory | After successful enable, the system stores the secret and the hashed recovery codes on the User record atomically (single write). |
| FR-005 | Mandatory | Recovery codes are stored hashed; the plaintext codes are visible exactly once at setup time. |
| FR-006 | Mandatory | When 2FA is enabled, the username+password login path returns `state: 2fa_required` and does NOT issue a session token until the second factor is verified. |
| FR-007 | Mandatory | The verify endpoint accepts EITHER a 6-digit TOTP code OR a recovery code, in that order. |
| FR-008 | Mandatory | A successful recovery-code verification removes the code from the user's stored set. |
| FR-009 | Mandatory | The disable endpoint requires a valid second-factor proof and atomically wipes the secret + recovery codes. |
| FR-010 | Mandatory | The verify endpoint is rate-limited identically to the login endpoint (5 failed attempts per IP per 60s). |

### Non-functional

| ID | Status | Threshold |
|---|---|---|
| NFR-001 | Mandatory | TOTP verification window accepts ±1 step around the current 30-second window (RFC 6238 tolerance). |
| NFR-002 | Mandatory | Recovery code comparison uses constant-time equality (`hash_equals`) to prevent timing attacks. |
| NFR-003 | Mandatory | TOTP secret generation uses cryptographically secure random bytes (`random_bytes`). |
| NFR-004 | Mandatory | The setup endpoint completes within 100 ms p95 (no external calls; pure CPU). |
| NFR-005 | Mandatory | Verify completes within 100 ms p95 (CPU-bound TOTP + hash check). |

### Constraints

| ID | Status | Constraint |
|---|---|---|
| C-001 | Mandatory | The 2FA storage extension cannot break existing `User` entity consumers: existing `User::fromStorage` callers must continue to work without changes when 2FA fields are absent. |
| C-002 | Mandatory | All new HTTP routes must register via `AuthOidcRouteServiceProvider` (the L4 routing layer for L1 auth/oidc per CLAUDE.md), not via `packages/auth/` directly. |
| C-003 | Mandatory | The `TwoFactorService` cannot import from any layer higher than L1 (per the Waaseyaa layer architecture). |
| C-004 | Mandatory | Every public method on `TwoFactorManager` must have at least one production caller after this mission lands. |
| C-005 | Mandatory | The dead-code baseline must drop by exactly the six `TwoFactorManager` entries currently in `phpstan-dead-code-baseline.neon`. No new entries may appear. |

## Success criteria

| ID | Metric | How verified |
|---|---|---|
| SC-001 | Users can complete the enable → log out → log in → verify TOTP flow in a single integration test, end to end. | `tests/Integration/Phase??/TwoFactorE2ETest.php` passes. |
| SC-002 | Users can fall back to recovery codes during login, and consumed codes are rejected on re-use. | Same integration test asserts recovery consumption. |
| SC-003 | `composer check-dead-code` reports zero `TwoFactorManager` baseline entries after this mission lands. | `grep -c 'TwoFactorManager' phpstan-dead-code-baseline.neon` returns 0. |
| SC-004 | `composer verify` is green on the merge commit. | CI status check `verify` passes on the merging PR. |
| SC-005 | The 2FA contract is documented at the framework spec level. | `docs/specs/access-control.md` has a Two-Factor Authentication section; `docs/specs/two-factor-auth.md` exists with the full contract. |
| SC-006 | Issue #1499 closes via the `Closes #1499` footer in the merge commit. | GitHub auto-closes issue on merge to main. |

## Key entities

| Entity | Role | Net change in this mission |
|---|---|---|
| `User` | Existing entity. Gains two new fields. | +2 fields, +1 migration |
| `TwoFactorCredential` (value object) | Carries setup result (secret, QR URI, plaintext recovery codes). Not persisted. | +1 file |
| `TwoFactorService` | New L1 service. Composes existing `TwoFactorManager` + `EntityTypeManager`. | +1 file |
| `SetupTwoFactorController` / `EnableTwoFactorController` / `VerifyTwoFactorController` / `DisableTwoFactorController` | New HTTP controllers in `packages/auth/src/Controller/`. | +4 files |
| `LoginController` | Existing. Modified to emit `state: 2fa_required` when applicable. | edit |
| `AuthOidcRouteServiceProvider` | Existing. Registers four new routes. | edit |
| `AuthServiceProvider` | Existing. Binds `TwoFactorService` in DI. | edit |
| Migration | New migration extending `users` table. | +1 file |

## Assumptions

- `User` entity supports adding nullable fields without a sweeping refactor (other entities have done this).
- The encryption-at-rest story for secrets is **deferred to a follow-up**; v1.0 stores Base32 secrets in plaintext with a TODO marker. Tracked separately if needed; this mission does not block on it.
- `EntityTypeManager::getStorage('user')` is the supported way for a service in `packages/auth/` to read/write Users.
- The existing `RateLimiterInterface` pattern (used by `LoginController`) generalizes to the verify endpoint.
- No UI changes in `packages/admin/` are required by this mission; the admin SPA can adopt later via the four new HTTP endpoints.

## Out of scope

- Encrypted-at-rest TOTP secrets (deferred — see Assumptions).
- WebAuthn / FIDO2 / passkeys.
- SMS-based 2FA.
- Push notifications.
- Admin-side "force 2FA for all users" enforcement policy.
- Recovery via account email (the existing forgot-password path covers credential reset more broadly).

## WP outline (for /spec-kitty.plan)

The implementation breaks into six work packages. Sequencing is documented here for downstream planning; the planner is free to revise.

- **WP01 — Storage:** Extend User entity with two fields + migration.
- **WP02 — Service:** Implement `TwoFactorService` + `TwoFactorCredential` value object + unit tests.
- **WP03 — Controllers:** Four HTTP controllers + unit tests.
- **WP04 — Login integration:** Modify `LoginController` to emit `state: 2fa_required`; register routes in `AuthOidcRouteServiceProvider`; bind DI in `AuthServiceProvider`.
- **WP05 — Integration tests:** E2E test asserting enable → verify TOTP → success; recovery-code flow; disable flow.
- **WP06 — Wrap-up:** Spec updates (`docs/specs/access-control.md` + new `docs/specs/two-factor-auth.md`), baseline regen confirming 6 TwoFactorManager entries dropped, CHANGELOG entry, full `composer verify`.

## References

- Issue: https://github.com/waaseyaa/framework/issues/1499
- Existing primitives: `packages/auth/src/TwoFactorManager.php`
- Existing pattern to follow: `packages/auth/src/Controller/LoginController.php`
- Routing pattern: `packages/routing/src/AuthOidcRouteServiceProvider.php`
- Dead-code audit: `docs/audits/2026-05-17-dead-code-baseline-audit.md`
