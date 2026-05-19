---
work_package_id: WP01
title: Exception + interface addition
dependencies: []
requirement_refs:
- FR-003
- FR-010
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-sql-entity-query-access-checking-01KRYP15
base_commit: 22337d0fd7ded09e8ac80c88c88b35df0bef8a51
created_at: '2026-05-18T23:51:22.376332+00:00'
subtasks:
- T001
- T002
- T003
shell_pid: "457878"
agent: "claude:opus-4-7:reviewer:reviewer"
history:
- date: '2026-05-18T23:44:03Z'
  actor: tasks-skill
  event: drafted
authoritative_surface: packages/entity-storage/src/Exception/
execution_mode: code_change
owned_files:
- packages/entity-storage/src/Exception/MissingQueryAccountException.php
- packages/entity-storage/tests/Unit/Exception/MissingQueryAccountExceptionTest.php
- packages/entity/src/Storage/EntityQueryInterface.php
tags: []
---

# WP01 — Exception + interface addition

## Objective

Ship the two foundation surfaces every other WP in this mission depends on:

1. A new `MissingQueryAccountException` that `SqlEntityQuery::execute()` will throw when access checking is enabled but no account is bound.
2. An addition to `EntityQueryInterface` — the new `setAccount(?AccountInterface $account): static` method — so consumers can rely on the contract regardless of storage backend.

Both deliverables are small, but they unblock WP02's filter implementation and WP03's consumer sweep.

## Context

- Spec FRs in scope: **FR-003, FR-010**.
- Constraints applied: **C-001** (PHP 8.5+, strict types), **C-006** (account-null + check-enabled MUST throw).
- Data-model authoritative: [data-model.md](../data-model.md) §"MissingQueryAccountException" and §"EntityQueryInterface contract addition".
- Contract authoritative: [contracts/entity-query-interface-additions.md](../contracts/entity-query-interface-additions.md).
- This is the only WP in the mission with **no upstream dependency** — WP02 onwards consume what lands here.

## Branch strategy

Planning + merge target: `main`. Lane allocated by `spec-kitty agent mission finalize-tasks`. Lane worktrees lack `vendor/` — first action after `cd` is `composer install`.

---

## Subtask T001 — `MissingQueryAccountException`

**Purpose:** Define the fail-closed exception that `SqlEntityQuery::execute()` will throw when access checking is enabled but no account has been bound. This is the security-critical default — silent bypass is rejected by design.

**Steps:**

1. Create the file at `packages/entity-storage/src/Exception/MissingQueryAccountException.php`:
   - `declare(strict_types=1);`
   - Namespace: `Waaseyaa\EntityStorage\Exception` (confirm against existing exception files in that package's `Exception/` directory; mirror exactly).
   - Class: `final class MissingQueryAccountException extends \RuntimeException`
   - **Constructor visibility:** `private` (so all instances flow through the named factory).
   - Class-level `@api` PHPDoc — the exception is part of the public surface implementers may catch.
2. Add the named factory:
   ```php
   public static function forQuery(\Waaseyaa\Entity\EntityTypeInterface $entityType): self
   {
       return new self(sprintf(
           'Cannot execute SqlEntityQuery for entity type "%s": access checking is enabled '
           . 'but no account is bound. Call setAccount() before execute(), or call '
           . 'accessCheck(false) for system contexts.',
           $entityType->id(),
       ));
   }
   ```
3. Verify the `Exception/` directory exists. If not, the `entity-storage` package may use a flat layout — check `ls packages/entity-storage/src/` and place the file alongside other exception classes following the local convention.

**Files:**
- `packages/entity-storage/src/Exception/MissingQueryAccountException.php` (NEW)

**Validation:**
- [ ] PSR-4 autoload resolves the class (`composer dump-autoload -o` succeeds; the class is reachable).
- [ ] PHPStan level 5 passes on the new file.
- [ ] cs-check passes.

---

## Subtask T002 — Add `setAccount()` to `EntityQueryInterface`

**Purpose:** Promote the account-binding pattern to the interface so future non-SQL backends are required to honour it and so consumers can type-hint against the interface (not the concrete `SqlEntityQuery`).

**Steps:**

1. Open `packages/entity/src/Storage/EntityQueryInterface.php`.
2. Add the new method declaration alongside the existing ones:
   ```php
   /**
    * Bind the account used for the access check. Pass null to clear any bound account.
    * Chainable.
    *
    * When the access check is enabled (the default) and no account is bound at execute() time,
    * implementations MUST throw MissingQueryAccountException — silent bypass is forbidden.
    *
    * @api
    */
   public function setAccount(?\Waaseyaa\Access\AccountInterface $account): static;
   ```
3. Sanity-check the file:
   - It already declares the namespace and existing methods (`condition`, `exists`, `notExists`, `sort`, `range`, `count`, `accessCheck`, `execute`).
   - Keep all existing methods unchanged. Only this one new method is added.
4. Verify `packages/entity` is allowed to import `Waaseyaa\Access\AccountInterface` (both packages are at Layer 1 per the constitution — same-layer import is permitted). `bin/check-package-layers` will validate.

**Files:**
- `packages/entity/src/Storage/EntityQueryInterface.php` (EDIT, additive)

**Validation:**
- [ ] PHPStan level 5 passes — the interface change does not yet require an implementation (WP02 ships that), but type-only references are valid.
- [ ] cs-check passes.
- [ ] `bin/check-package-layers` exits 0 (no upward import).

---

## Subtask T003 — Unit test for the exception

**Purpose:** Lock in the factory's message shape and the `\RuntimeException` parentage. Cheap regression insurance.

**Steps:**

1. Create `packages/entity-storage/tests/Unit/Exception/MissingQueryAccountExceptionTest.php`.
2. Test cases:
   - `forQuery()` returns an instance whose message contains the entity type id, and whose message mentions both `setAccount()` and `accessCheck(false)` (so future agents can find resolution paths by reading the exception alone).
   - The instance is `instanceof \RuntimeException`.
   - The constructor cannot be called directly from outside the class (private constructor — reflection check).
3. Use the existing test base class for unit tests in this package (typically `PHPUnit\Framework\TestCase`; mirror an existing exception test if one exists).
4. PHPUnit 10.5 attributes: `#[Test]` and `#[CoversClass(MissingQueryAccountException::class)]`.

**Files:**
- `packages/entity-storage/tests/Unit/Exception/MissingQueryAccountExceptionTest.php` (NEW)

**Validation:**
- [ ] `./vendor/bin/phpunit packages/entity-storage/tests/Unit/Exception/MissingQueryAccountExceptionTest.php` green.
- [ ] cs-check passes on the test file.

---

## Definition of Done

- [ ] T001..T003 checkboxes flipped.
- [ ] `MissingQueryAccountException::forQuery()` instantiable; message reads as documented.
- [ ] `EntityQueryInterface::setAccount()` declared; no implementation yet (WP02 will ship `SqlEntityQuery`'s implementation, which today is the only implementation of the interface).
- [ ] Gates green: cs-check, phpstan, layers, dead-code, composer-policy.

## Risks & mitigations

1. **Interface change is technically breaking.** *Mitigation:* today there is exactly one implementation (`SqlEntityQuery`); a Composer search confirms no third-party implementations exist (the spec's external-consumer verification grep at WP05 catches this). The change ships in v1 CHANGELOG under § Changed.
2. **Exception namespace mismatch.** *Mitigation:* before writing the file, run `ls packages/entity-storage/src/Exception/` (if it exists) or `grep -l "namespace Waaseyaa\\\\EntityStorage" packages/entity-storage/src/Exception/*.php` to confirm the canonical namespace shape. Match it.

## Reviewer guidance

- Verify the exception's constructor is `private` and the only construction path is `forQuery()`.
- Verify the interface addition uses `?AccountInterface` (nullable; explicit clearing is supported) and `: static` (chainable).
- Spot-check that the message in `forQuery()` names BOTH escape hatches: `setAccount()` and `accessCheck(false)`. Future agents debugging this exception will be reading the message, not the docs.

## Implementation command

```
spec-kitty agent action implement WP01 --agent <name>
```

## Activity Log

- 2026-05-18T23:51:23Z – claude:sonnet:implementer:implementer – shell_pid=455031 – Assigned agent via action command
- 2026-05-18T23:58:20Z – claude:sonnet:implementer:implementer – shell_pid=455031 – Ready for review: MissingQueryAccountException with private ctor + forQuery() factory; EntityQueryInterface::setAccount() addition with same-layer L1 access import; minimal SqlEntityQuery::setAccount() stub so interface change ships independently (WP02 replaces it); 3 unit tests green; full entity + entity-storage suites green (1143 tests); cs-check, phpstan, layers, dead-code, composer-policy all green. NOTE: spec-kitty guard surfaced warnings for packages/entity{,-storage}/composer.json + SqlEntityQuery.php — these are the orchestrator-authorized supporting edits (composer require waaseyaa/access on both packages so AccountInterface autoloads; SqlEntityQuery stub) called out in the implementer mandate; not silent scope creep.
- 2026-05-18T23:59:03Z – claude:opus-4-7:reviewer:reviewer – shell_pid=457878 – Started review via action command
- 2026-05-19T00:01:58Z – claude:opus-4-7:reviewer:reviewer – shell_pid=457878 – Approved. Exception + interface land cleanly: forQuery() factory names entity-type id, setAccount(), and accessCheck(false); private ctor enforced via reflection test; @api on class + factory + interface method. Interface change is backwards-compatible (chainable, nullable). SqlEntityQuery stub returns $this and accepts ?AccountInterface — correct contract satisfaction; unset($account) is idiomatic for property-less stubs and avoids PHPStan property.onlyWritten without ignore comments. Composer cycle entity ⇄ access exists (access already required entity; this WP adds entity → access) but resolves cleanly via path repos at dev-main: composer update --dry-run, install, phpstan, dead-code, layers (same-L1 allowed), composer-policy, cs-check, 669+474 entity-storage/entity tests, and the new 3-test exception suite all green. The 3 orchestrator-authorized supporting edits (stub + 2 composer.json files) are minimal, scope-bound, and necessary. SqlEntityQuery::accessCheck() body untouched — WP02 territory preserved. Cycle is a known smell worth a future follow-up (e.g., move AccountInterface to a lower layer or to a tiny waaseyaa/account-contract package) but does NOT block WP01 — both packages are L1 and composer handles the cycle. Recommend WP02 implementer revisit the cycle question.
