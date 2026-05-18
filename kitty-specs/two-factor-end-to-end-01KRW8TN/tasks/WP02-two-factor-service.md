---
work_package_id: WP02
title: TwoFactorService + value object
dependencies:
- WP01
requirement_refs:
- FR-003
- FR-004
- FR-005
- FR-007
- FR-008
- FR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: single-lane
subtasks:
- T004
- T005
- T006
- T007
- T008
history: []
authoritative_surface: packages/auth/src/
execution_mode: code_change
owned_files:
- packages/auth/src/TwoFactorService.php
- packages/auth/src/TwoFactorSetupResult.php
- packages/auth/tests/Unit/TwoFactorServiceTest.php
tags: []
---

# WP02 — TwoFactorService + value object

## Objective

Build the L1 service that composes the existing `TwoFactorManager` primitives with `EntityTypeManager` for User persistence. The service is the SINGLE caller of all 6 currently-baselined `TwoFactorManager` methods.

## Branch strategy

Planning base: `main`. Final merge target: `main`. Execution worktree per `lanes.json`.

## Context

Per spec.md service contract:

```
TwoFactorService:
  setup(User): TwoFactorSetupResult     # secret + QR URI + plaintext codes; not persisted yet
  enable(User, secret, plaintextCodes, firstCode): bool
                                        # verifies firstCode; if ok, hashes codes + persists
  verify(User, code): bool              # TOTP first; recovery fallback (consumes code)
  disable(User): void                   # wipes secret + codes atomically
  isEnabled(User): bool
```

Recovery codes hash with Argon2id (matches `User::setRawPassword` convention).

## Subtasks

### T004 — `TwoFactorSetupResult` value object

Create `packages/auth/src/TwoFactorSetupResult.php`. Carries `secret` (Base32 string), `qrCodeUri` (otpauth:// URI), `recoveryCodes` (list<string>, plaintext, displayed exactly once).

```php
<?php declare(strict_types=1);
namespace Waaseyaa\Auth;

/**
 * Result of a successful 2FA setup call. Carries the data the user needs to
 * complete enable: the Base32 secret, the otpauth:// QR URI for their
 * authenticator app, and the eight plaintext recovery codes. Plaintext codes
 * are displayed ONCE; only hashes are persisted on enable.
 *
 * @api
 */
final readonly class TwoFactorSetupResult
{
    /** @param list<string> $recoveryCodes */
    public function __construct(
        public string $secret,
        public string $qrCodeUri,
        public array $recoveryCodes,
    ) {}
}
```

### T005 — `TwoFactorService` skeleton

Create `packages/auth/src/TwoFactorService.php`. Constructor takes `TwoFactorManager` + `EntityTypeManager`. Define all 5 method signatures with phpdoc; implementations stubbed with `throw new \LogicException('not implemented yet')`.

Class header:
```php
<?php declare(strict_types=1);
namespace Waaseyaa\Auth;

use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\User\User;

/**
 * Orchestrates the 2FA enable/verify/disable flow on a User. Composes the
 * existing TwoFactorManager primitives with EntityTypeManager for persistence.
 *
 * @api
 */
final class TwoFactorService
{
    public function __construct(
        private readonly TwoFactorManager $manager,
        private readonly EntityTypeManager $entityTypeManager,
    ) {}
    // ... methods follow
}
```

### T006 — `setup()` implementation

```php
public function setup(User $user): TwoFactorSetupResult
{
    if ($this->isEnabled($user)) {
        throw new \RuntimeException('2FA already enabled for this user. Disable first.');
    }
    $secret = $this->manager->generateSecret();
    $codes = $this->manager->generateRecoveryCodes();
    $qrUri = $this->manager->getQrCodeUri(
        $secret,
        $user->mail ?? '',
        'Waaseyaa', // TODO: pull from config.app.name
    );
    return new TwoFactorSetupResult(
        secret: $secret,
        qrCodeUri: $qrUri,
        recoveryCodes: $codes,
    );
}
```

### T007 — `enable()` / `verify()` / `disable()` / `isEnabled()`

```php
public function enable(User $user, string $secret, array $plaintextCodes, string $firstCode): bool
{
    if (!$this->manager->verifyCode($secret, $firstCode)) {
        return false;
    }
    $hashes = array_map(
        fn (string $code) => password_hash($code, PASSWORD_ARGON2ID),
        $plaintextCodes,
    );
    $user->setTwoFactorSecret($secret);
    $user->setTwoFactorRecoveryCodesHash(array_values($hashes));
    $this->entityTypeManager->getStorage('user')->save($user);
    return true;
}

public function verify(User $user, string $code): bool
{
    $secret = $user->getTwoFactorSecret();
    if ($secret === null) {
        return false;
    }
    // TOTP first
    if ($this->manager->verifyCode($secret, $code)) {
        return true;
    }
    // Recovery code fallback
    $hashes = $user->getTwoFactorRecoveryCodesHash() ?? [];
    foreach ($hashes as $idx => $hash) {
        if (password_verify($code, $hash)) {
            unset($hashes[$idx]);
            $user->setTwoFactorRecoveryCodesHash(array_values($hashes));
            $this->entityTypeManager->getStorage('user')->save($user);
            return true;
        }
    }
    return false;
}

public function disable(User $user): void
{
    $user->setTwoFactorSecret(null);
    $user->setTwoFactorRecoveryCodesHash(null);
    $this->entityTypeManager->getStorage('user')->save($user);
}

public function isEnabled(User $user): bool
{
    return $user->getTwoFactorSecret() !== null;
}
```

### T008 — `TwoFactorServiceTest`

Create `packages/auth/tests/Unit/TwoFactorServiceTest.php`. Use an in-memory User + a stub EntityTypeManager. Cover:
- `setup` returns a TwoFactorSetupResult with non-empty secret, QR URI starting `otpauth://totp/`, and 8 recovery codes.
- `setup` throws when 2FA already enabled.
- `enable` returns false on wrong firstCode and doesn't persist.
- `enable` returns true on correct firstCode, persists secret + hashed codes.
- `verify` accepts current TOTP via `manager->getCurrentCode($secret)` then `verify($user, $code)`.
- `verify` accepts plaintext recovery code, then re-verify on same code returns false (consumed).
- `disable` clears both fields.
- `isEnabled` correctly reflects state.

Use real `TwoFactorManager` (no mock — it's deterministic per the existing tests).

## Definition of Done

- All five service methods implemented and unit-tested.
- `TwoFactorSetupResult` exists as a `@api` value object.
- All 6 `TwoFactorManager` methods reached via at least one test path.
- `composer cs-check`, `composer phpstan`, `composer test` green on touched files.

## Risks

- **Risk:** `EntityTypeManager::getStorage('user')` returns a `StorageInterface` that may not expose `save()`. *Mitigation:* check `packages/entity/src/EntityTypeManager.php` and `Storage/` — likely fine, all existing entity-saving code uses this pattern.
- **Risk:** Password Argon2id is slow under default cost. *Mitigation:* keep at default (3 iterations, 64MB) — 8 codes × ~10ms = 80ms total. Within NFR-005.

## Reviewer guidance

- Verify Argon2id is the cipher (not bcrypt) — matches User::setRawPassword.
- Verify recovery-code list is re-indexed after consumption (no holes).
- Verify `setup()` does NOT persist — only `enable()` does.

## Implement command

```bash
spec-kitty agent action implement WP02 --agent <name>
```
