---
work_package_id: WP01
title: User entity 2FA fields
dependencies: []
requirement_refs:
- FR-001
planning_base_branch: main
merge_target_branch: main
branch_strategy: single-lane
subtasks:
- T001
- T002
- T003
history: []
authoritative_surface: packages/user/src/
execution_mode: code_change
owned_files:
- packages/user/src/User.php
- packages/user/tests/Unit/UserTwoFactorFieldsTest.php
tags: []
---

# WP01 — User entity 2FA fields

## Objective

Extend the `User` entity with two `#[Field]`-annotated public properties (`two_factor_secret` + `two_factor_recovery_codes_hash`) and their setter/clearer methods. These persist via the framework's `_data` JSON blob mechanism — **no schema migration required**.

## Branch strategy

Planning base: `main`. Final merge target: `main`. Execution worktree assigned by `lanes.json` after `finalize-tasks` — all WPs in this mission run on a single lane.

## Context

Waaseyaa entity storage policy (per CLAUDE.md): "SqlSchemaHandler adds a `_data` TEXT column. SqlEntityStorage::splitForStorage() puts non-schema values into it as JSON; mapRowToEntity() merges them back on load." Schema columns are only those declared in `entityKeys`. Everything else — including `#[Field]` properties — round-trips via `_data` automatically.

Existing pattern to mirror in User.php (around line 60):
```php
#[Field(type: 'email', label: 'Email address', description: '...', settings: ['weight' => 5])]
public ?string $mail = null;
```

## Subtasks

### T001 — Add `two_factor_secret` property + setter/clearer

**Purpose:** Carry the Base32-encoded TOTP secret on User. Nullable; `null` means 2FA is disabled.

**Steps:**

1. Open `packages/user/src/User.php`.
2. After the `email_verified` field (around line 60), add:
   ```php
   #[Field(label: 'Two-factor secret', description: 'Base32 TOTP secret. null when 2FA disabled.', settings: ['weight' => 50, 'internal' => true])]
   public ?string $two_factor_secret = null;
   ```
3. Add a setter near the existing `setEmail` / `setPassword` cluster:
   ```php
   public function setTwoFactorSecret(?string $secret): static
   {
       return $this->set('two_factor_secret', $secret);
   }
   ```
4. Add a getter:
   ```php
   public function getTwoFactorSecret(): ?string
   {
       return $this->get('two_factor_secret');
   }
   ```

**Files touched:** `packages/user/src/User.php` (+1 property, +2 methods, ~15 LOC).

### T002 — Add `two_factor_recovery_codes_hash` property + setter/clearer

**Purpose:** Carry the list of hashed recovery codes. Empty list / `null` means none.

**Steps:**

1. In `packages/user/src/User.php`, after `two_factor_secret`, add:
   ```php
   /** @var list<string>|null */
   #[Field(type: 'json', label: 'Two-factor recovery codes', description: 'Argon2id-hashed recovery codes; null when 2FA disabled.', settings: ['weight' => 51, 'internal' => true])]
   public ?array $two_factor_recovery_codes_hash = null;
   ```
2. Add setter:
   ```php
   /** @param list<string>|null $hashes */
   public function setTwoFactorRecoveryCodesHash(?array $hashes): static
   {
       return $this->set('two_factor_recovery_codes_hash', $hashes);
   }
   ```
3. Add getter:
   ```php
   /** @return list<string>|null */
   public function getTwoFactorRecoveryCodesHash(): ?array
   {
       return $this->get('two_factor_recovery_codes_hash');
   }
   ```

**Files touched:** Same file (+1 property, +2 methods).

### T003 — Unit test for round-trip via `_data`

**Purpose:** Prove that setting these new fields, persisting, and reloading round-trips correctly.

**Steps:**

1. Create `packages/user/tests/Unit/UserTwoFactorFieldsTest.php`.
2. The test creates a User via `User::make(['name' => 'alice', 'mail' => 'alice@example.com'])`, sets the two new fields, verifies `get()` returns the values, then asserts JSON round-trip via `EntityValuesSnapshot` (or a similar in-memory persistence pattern used by existing user tests).
3. Reference existing tests in `packages/user/tests/Unit/` for the test base class + setUp pattern. PHPUnit 10.5 attributes: `#[Test]`, `#[CoversClass(User::class)]`.

**Validation:**
- Test passes.
- Test class autoloaded by package autoload.

## Definition of Done

- Two new properties on `User` with `#[Field]` attributes.
- Four new methods (set/get pairs).
- One new test file `UserTwoFactorFieldsTest.php` passes.
- `composer cs-check` clean on touched files.
- `composer phpstan` clean.
- Existing User tests still pass.

## Risks

- **Risk:** Existing `User::fromStorage()` callers break if reflection scanning chokes on the new properties. *Mitigation:* defaults are `null`/`null`; existing rows load without these keys and fall to defaults.
- **Risk:** `#[Field(type: 'json', ...)]` is not a registered field type. *Mitigation:* Check `packages/field/src/Item/` — `JsonItem.php` should exist (it's in the standard field set). If `type: 'json'` is not the right field type id, look at how other entities store list-of-strings.

## Reviewer guidance

- Verify field weight values (50, 51) don't collide with existing user fields.
- Verify the `settings.internal => true` flag actually suppresses these fields from default admin-surface listings (look up how `email_verified` is handled).
- No symfony imports needed for this WP.

## Implement command

```bash
spec-kitty agent action implement WP01 --agent <name>
```
