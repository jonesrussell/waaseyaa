# Tasks: Two-Factor Authentication End-to-End

**Mission:** `two-factor-end-to-end-01KRW8TN`
**Spec:** [spec.md](./spec.md) | **Plan:** [plan.md](./plan.md)
**Total subtasks:** 24 across 6 WPs (single lane, sequential)

## Subtask Index

| ID | Description | WP | Parallel |
|---|---|---|---|
| T001 | Add `two_factor_secret` `#[Field]` property + setter/clearer to User | WP01 |  |
| T002 | Add `two_factor_recovery_codes_hash` `#[Field]` property + setter/clearer to User | WP01 |  |
| T003 | Unit test on User: round-trip via `_data` blob | WP01 |  |
| T004 | Create `TwoFactorSetupResult` value object | WP02 |  |
| T005 | Skeleton `TwoFactorService` class with DI constructor | WP02 |  |
| T006 | Implement `setup()` method | WP02 |  |
| T007 | Implement `enable()` / `verify()` / `disable()` / `isEnabled()` | WP02 |  |
| T008 | `TwoFactorServiceTest` covering all 5 methods | WP02 |  |
| T009 | `SetupTwoFactorController` + unit test | WP03 | [P] |
| T010 | `EnableTwoFactorController` + unit test | WP03 | [P] |
| T011 | `VerifyTwoFactorController` + unit test | WP03 | [P] |
| T012 | `DisableTwoFactorController` + unit test | WP03 | [P] |
| T013 | Shared `TwoFactorJsonResponse` helper (only if duplication emerges) | WP03 |  |
| T014 | Modify `LoginController` to inject `TwoFactorService` + emit `state: 2fa_required` | WP04 |  |
| T015 | Update `LoginControllerTest` with the 2fa_required path | WP04 |  |
| T016 | Register 4 routes in `AuthOidcRouteServiceProvider` | WP04 |  |
| T017 | Bind `TwoFactorService` singleton in `AuthServiceProvider` | WP04 |  |
| T018 | E2E `testTotpFlow` | WP05 | [P] |
| T019 | E2E `testRecoveryFlow` | WP05 | [P] |
| T020 | E2E `testDisableFlow` | WP05 | [P] |
| T021 | Update `docs/specs/access-control.md` (Two-Factor section) | WP06 |  |
| T022 | Create `docs/specs/two-factor-auth.md` (full contract) | WP06 |  |
| T023 | Regenerate `phpstan-dead-code-baseline.neon` — verify 6 TwoFactorManager entries dropped | WP06 |  |
| T024 | CHANGELOG `[Unreleased]` entry | WP06 |  |

## Work Packages

### WP01 — User entity 2FA fields

**Goal:** Two new `#[Field]` properties on `User` for the TOTP secret and the hashed recovery codes. Persists through the existing `_data` JSON blob mechanism — no migration.
**Priority:** Foundation (everything else depends).
**Independent test:** A unit test creates a User, sets the secret + codes, saves via in-memory storage, reloads, and asserts both fields round-trip.

- [ ] T001 Add `two_factor_secret` `#[Field]` property + setter/clearer to User (WP01)
- [ ] T002 Add `two_factor_recovery_codes_hash` `#[Field]` property + setter/clearer to User (WP01)
- [ ] T003 Unit test on User: round-trip via `_data` blob (WP01)

**Prompt:** [WP01-user-entity-2fa-fields.md](./tasks/WP01-user-entity-2fa-fields.md)
**Estimated size:** ~200 lines.
**Dependencies:** none.

### WP02 — TwoFactorService + value object

**Goal:** New L1 service composing `TwoFactorManager` + `EntityTypeManager`. Provides setup/enable/verify/disable/isEnabled.
**Priority:** Foundation (controllers depend on this).
**Independent test:** Unit test instantiates service with in-memory User storage and asserts each method.

- [ ] T004 Create `TwoFactorSetupResult` value object (WP02)
- [ ] T005 Skeleton `TwoFactorService` class with DI constructor (WP02)
- [ ] T006 Implement `setup()` method (WP02)
- [ ] T007 Implement `enable()` / `verify()` / `disable()` / `isEnabled()` (WP02)
- [ ] T008 `TwoFactorServiceTest` covering all 5 methods (WP02)

**Prompt:** [WP02-two-factor-service.md](./tasks/WP02-two-factor-service.md)
**Estimated size:** ~350 lines.
**Dependencies:** WP01.

### WP03 — Four HTTP controllers

**Goal:** SetupTwoFactor / EnableTwoFactor / VerifyTwoFactor / DisableTwoFactor controllers. Each follows the LoginController pattern (JSON in, JSON:API envelope out, RateLimiter where appropriate).
**Priority:** Surfaces the service.
**Independent test:** Each controller has a unit test for happy path + the named failure modes from the edge case matrix.

- [ ] T009 `SetupTwoFactorController` + unit test (WP03)
- [ ] T010 `EnableTwoFactorController` + unit test (WP03)
- [ ] T011 `VerifyTwoFactorController` + unit test (WP03)
- [ ] T012 `DisableTwoFactorController` + unit test (WP03)
- [ ] T013 Shared `TwoFactorJsonResponse` helper (only if duplication emerges) (WP03)

**Prompt:** [WP03-http-controllers.md](./tasks/WP03-http-controllers.md)
**Estimated size:** ~450 lines.
**Dependencies:** WP02.
**Parallel:** T009–T012 are independent files; could parallelize if needed.

### WP04 — LoginController integration + routes + DI

**Goal:** LoginController returns `state: 2fa_required` when applicable. Four routes registered. DI bindings landed.
**Priority:** Wiring step.
**Independent test:** LoginControllerTest covers the 2fa_required branch.

- [ ] T014 Modify `LoginController` to inject `TwoFactorService` + emit `state: 2fa_required` (WP04)
- [ ] T015 Update `LoginControllerTest` with the 2fa_required path (WP04)
- [ ] T016 Register 4 routes in `AuthOidcRouteServiceProvider` (WP04)
- [ ] T017 Bind `TwoFactorService` singleton in `AuthServiceProvider` (WP04)

**Prompt:** [WP04-login-integration-routing.md](./tasks/WP04-login-integration-routing.md)
**Estimated size:** ~300 lines.
**Dependencies:** WP02, WP03.

### WP05 — Integration E2E tests

**Goal:** Three E2E test methods covering happy path, recovery, disable.
**Priority:** Acceptance.
**Independent test:** Each test method passes against a full booted kernel.

- [ ] T018 E2E `testTotpFlow` (WP05)
- [ ] T019 E2E `testRecoveryFlow` (WP05)
- [ ] T020 E2E `testDisableFlow` (WP05)

**Prompt:** [WP05-integration-tests.md](./tasks/WP05-integration-tests.md)
**Estimated size:** ~250 lines.
**Dependencies:** WP04.
**Parallel:** T018–T020 are independent test methods in one file; trivially parallelizable in execution.

### WP06 — Wrap-up

**Goal:** Spec docs updated, dead-code baseline regenerated, CHANGELOG entry, full `composer verify` green.
**Priority:** Close out.
**Independent test:** `grep -c TwoFactorManager phpstan-dead-code-baseline.neon` returns 0 + `composer verify` exits 0.

- [ ] T021 Update `docs/specs/access-control.md` (Two-Factor section) (WP06)
- [ ] T022 Create `docs/specs/two-factor-auth.md` (full contract) (WP06)
- [ ] T023 Regenerate `phpstan-dead-code-baseline.neon` — verify 6 TwoFactorManager entries dropped (WP06)
- [ ] T024 CHANGELOG `[Unreleased]` entry (WP06)

**Prompt:** [WP06-wrap-up.md](./tasks/WP06-wrap-up.md)
**Estimated size:** ~200 lines.
**Dependencies:** WP05.

## Lane summary

All 6 WPs run on a single lane (sequential dependencies). No parallel WPs. Total expected effort: roughly one focused session.
