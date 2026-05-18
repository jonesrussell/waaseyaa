---
work_package_id: WP05
title: Integration E2E tests
dependencies:
- WP04
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-005
- FR-006
- FR-007
- FR-008
- FR-009
- FR-010
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T018
- T019
- T020
history: []
authoritative_surface: tests/Integration/PhaseTwoFactor/
execution_mode: code_change
owned_files:
- tests/Integration/PhaseTwoFactor/TwoFactorE2ETest.php
tags: []
---

# WP05 — Integration E2E tests

## Objective

Three E2E tests that boot a full kernel + in-memory SQLite + simulate HTTP requests through the router, covering happy path / recovery / disable flows.

## Branch strategy

Planning base: `main`. Final merge target: `main`. Execution worktree per `lanes.json`.

## Context

Existing integration test pattern: `tests/Integration/Phase??/*Test.php`. Look at any existing E2E test like `tests/Integration/Phase14/ListingPipelineIntegrationTest.php` for the kernel-boot + HTTP-simulation pattern.

Tests live at `tests/Integration/PhaseTwoFactor/TwoFactorE2ETest.php` — `Phase` prefix is the framework convention; this mission's slice gets its own phase directory.

## Subtasks

### T018 — `testTotpFlow`

```
Register user via /auth/register (or seed directly)
Log in via /auth/login → get session
POST /auth/2fa/setup → receive secret, qr, codes
POST /auth/2fa/enable with secret + recovery_codes + first_code (computed from TwoFactorManager::getCurrentCode($secret))
Log out
POST /auth/login with username+password
  → expect 200 with attributes.state == "2fa_required"
POST /auth/2fa/verify with current TOTP code
  → expect 200 with verified: true
```

### T019 — `testRecoveryFlow`

```
[enable 2FA as in testTotpFlow steps 1-5]
Log out
POST /auth/login → 2fa_required
POST /auth/2fa/verify with one of the recovery codes (the plaintext returned from setup)
  → expect 200, verified: true
POST /auth/2fa/verify with same recovery code again
  → expect 401 (consumed)
```

### T020 — `testDisableFlow`

```
[enable 2FA]
POST /auth/2fa/disable with current TOTP code
  → expect 200, enabled: false
Reload User → assert two_factor_secret is null, two_factor_recovery_codes_hash is null
Log out
POST /auth/login with username+password
  → expect 200 with NORMAL session (no 2fa_required)
```

## Test implementation notes

- Use `DBALDatabase::createSqlite(':memory:')` for in-memory storage per CLAUDE.md guidance.
- Authenticated requests need `_account` set; mimic SessionMiddleware behavior in test setUp.
- TOTP codes: use `TwoFactorManager::getCurrentCode($secret)` to compute the current code at the time of the verify call.
- The 30-second TOTP window: tests run fast; one window covers the whole test. No clock-mocking needed.

## Definition of Done

- All three test methods pass.
- Tests prove every FR-001..FR-010 from spec.md.
- `composer test --filter TwoFactorE2E` runs cleanly.

## Risks

- **Risk:** Phase directory `PhaseTwoFactor` collides with phpunit grouping conventions. *Mitigation:* check existing Phase directory naming; use a numeric prefix if convention requires (e.g., `Phase15TwoFactor`).
- **Risk:** Test boot time is significant. *Mitigation:* share kernel boot across test methods via `setUpBeforeClass` if needed.

## Reviewer guidance

- Verify all three tests assert the FULL response shape, not just status code.
- Verify recovery-code consumption is asserted via SECOND verify call.
- Verify disable test reloads User from storage (not just trusts the in-memory User object).

## Implement command

```bash
spec-kitty agent action implement WP05 --agent <name>
```
