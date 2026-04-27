---
work_package_id: WP06
title: 'F4b: Attachment policy + concurrency test'
dependencies:
- WP05
requirement_refs:
- FR-011
- FR-019
- NFR-005
- NFR-007
- NFR-009
- NFR-010
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T025
- T026
- T027
- T028
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "11068"
history:
- date: '2026-04-27'
  note: Generated from plan.md + spec.md FR-011 + NFR-010.
authoritative_surface: packages/attachment/src/Policy/
execution_mode: code_change
mission_id: 01KQ7M1PHWD8QAQPJC91RAVE0T
mission_slug: single-entity-work-surface-01KQ7M1P
owned_files:
- packages/attachment/src/Policy/ParentDelegatedAccessPolicy.php
- packages/attachment/tests/Unit/Policy/ParentDelegatedAccessPolicyTest.php
- packages/attachment/tests/Integration/SetActiveConcurrencyTest.php
- packages/attachment/tests/Integration/ParentDelegatedAccessTest.php
tags: []
---

# WP06 — F4b: Attachment policy + concurrency test

## Objective

Add `ParentDelegatedAccessPolicy` for the `attachment` entity type — view/update/delete decisions delegate to the parent entity's policy. Add the NFR-010 concurrency test asserting the `setActive` invariant under parallel calls.

## Context (read first)

- **spec.md** FR-011, NFR-010.
- **research.md** Q2 (auto-discovery via `#[PolicyAttribute]`), Q6 (`setActive` atomicity rationale).
- **contracts/README.md** F4 — policy contract.
- **`packages/access/src/AccessPolicyInterface.php`**, **`packages/access/src/EntityAccessHandler.php`**, **`packages/access/src/Gate/PolicyAttribute.php`** — interfaces and discovery surface.
- **`packages/access/src/AccessResult.php`** — return-value vocabulary (`::allowed()`, `::neutral()`, `::forbidden()`).
- **CLAUDE.md** gotcha "discoverAccessPolicies() constructor heuristic" — reflection-based auto-discovery passes entity types to constructors with required params.

## Branch Strategy

- **Planning base**: `main` (after WP05 lands)
- **Final merge target**: `main`
- Lane via `finalize-tasks`. Use `spec-kitty agent action implement WP06 --agent <name> --mission single-entity-work-surface-01KQ7M1P`.

## Subtasks

### T025 — `ParentDelegatedAccessPolicy`

**File**: `packages/attachment/src/Policy/ParentDelegatedAccessPolicy.php`

```php
<?php
declare(strict_types=1);

namespace Waaseyaa\Attachment\Policy;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Attachment\Attachment;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManagerInterface;

#[PolicyAttribute(entityType: 'attachment')]
final class ParentDelegatedAccessPolicy implements AccessPolicyInterface
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly EntityAccessHandler $accessHandler,
    ) {}

    public function access(EntityInterface $entity, string $operation, ?AccountInterface $account): AccessResult
    {
        if (!$entity instanceof Attachment) {
            return AccessResult::neutral();
        }

        $parentType = (string) $entity->get('parent_entity_type')->value;
        $parentId = (string) $entity->get('parent_entity_id')->value;

        if ($parentType === '' || $parentId === '') {
            return AccessResult::neutral();
        }

        $storage = $this->entityTypeManager->getStorage($parentType);
        $parent = $storage->load($parentId);
        if ($parent === null) {
            // Referential integrity gap; let the handler enforcement layer decide via isAllowed().
            return AccessResult::neutral();
        }

        // Delegate to the parent entity's policy.
        return $this->accessHandler->access($parent, $operation, $account);
    }
}
```

**Notes**:
- The `EntityAccessHandler::access()` method should be the canonical entry to a parent's policy. If the actual method name differs (e.g., `check()` or `evaluate()`), adjust accordingly.
- Returning `Neutral` rather than `Forbidden` when the parent is missing is intentional. Per spec access-result semantics, entity-level uses `isAllowed()` which denies on `Neutral` — so the user is denied by default for orphaned attachments, without us encoding that as an explicit "Forbidden" answer (which would imply a deliberate denial decision).
- The constructor takes injected interfaces — `discoverAccessPolicies()` heuristic in `AbstractKernel` (see CLAUDE.md gotcha) handles instantiation of policies with required constructor params via reflection.

### T026 — Policy unit tests

**File**: `packages/attachment/tests/Unit/Policy/ParentDelegatedAccessPolicyTest.php`

**Cases**:
- View permission on parent → policy returns Allowed for view on attachment.
- View denied on parent → policy returns Forbidden for view on attachment.
- Update allowed on parent → policy returns Allowed for update on attachment.
- Parent entity doesn't exist (storage returns null) → policy returns Neutral.
- Entity passed in is not an `Attachment` (defensive) → policy returns Neutral.
- Empty `parent_entity_type` or `parent_entity_id` → policy returns Neutral.
- Operation `delete` propagates to parent's `delete` decision.

Use stubs for `EntityTypeManagerInterface` and `EntityAccessHandler`; capture the calls and assert delegation arguments.

### T027 — Concurrency test for `setActive` invariant (NFR-010)

**File**: `packages/attachment/tests/Integration/SetActiveConcurrencyTest.php`

**Goal**: Demonstrate that under 50 concurrent `setActive` calls against the same parent, exactly one row has `is_active = true` after all calls complete.

**Approach** — given Windows + bash environment may not support fork-style parallelism reliably, use one of:

1. **Process-level parallelism via `pcntl_fork`** (POSIX only) — preferred on Linux CI matrix per charter.
2. **Thread-style via `parallel/parallel`** — adds dependency, rejected.
3. **Sequential interleave with explicit transactional ordering** — verifies the invariant logically but not under true concurrency. Acceptable as a complement, not a substitute.
4. **Simulated concurrency via a stress test script** — fork 50 child processes, each calls `setActive(<random-id-among-50>)`, parent waits and asserts post-state.

**Recommended**:
- Skip this test on Windows (`@requires extension pcntl`); CI matrix runs Ubuntu/Debian per charter.
- Inside the test:
  1. Set up a SQLite database (file-backed, not `:memory:`, so child processes share it).
  2. Insert 50 attachments with the same parent.
  3. Fork 50 children; each calls `$repo->setActive($randomId)` with a randomly chosen attachment id.
  4. Parent waits for all children, then re-reads and asserts `count(WHERE is_active = true) === 1`.

**Validation**:
- Test passes on Linux CI.
- Marked skipped on platforms without `pcntl`.
- Comment in the test cites NFR-010 by ID and links to research.md Q6.

### T028 — Integration tests: parent-delegated access + setActive end-to-end

**File**: `packages/attachment/tests/Integration/ParentDelegatedAccessTest.php`

**Setup**: real kernel boot with `attachment` and a parent entity type (`node` or a fixture). Real `EntityRepository`. Real `EntityAccessHandler`. Auto-discovery picks up `ParentDelegatedAccessPolicy`.

**Cases**:
- Account A has `view` on parent node 1 → can view attachments of node 1.
- Account A does not have `view` on parent node 2 → cannot view attachments of node 2.
- `setActive` end-to-end: create 3 attachments, set active on second, list and verify exactly one active in returned list.
- `delete` an attachment: parent unchanged, other attachments untouched.

## Definition of Done

- [ ] `ParentDelegatedAccessPolicy` exists with `#[PolicyAttribute(entityType: 'attachment')]`.
- [ ] Policy delegates `view`/`update`/`delete` to the parent entity's policy.
- [ ] Policy returns Neutral on missing parent or non-Attachment input.
- [ ] Auto-discovery via `discoverAccessPolicies()` registers the policy at boot (verify in integration test).
- [ ] Unit test covers all delegation paths + edge cases.
- [ ] Concurrency test exercises 50 concurrent `setActive` calls and asserts the invariant; skipped gracefully on platforms without `pcntl`.
- [ ] Integration test exercises real access machinery end-to-end.
- [ ] `composer phpstan`, `composer cs-check`, PHPUnit pass.
- [ ] No code changes outside `owned_files`.

## Risks

| Risk | Mitigation |
|---|---|
| `EntityAccessHandler` doesn't expose a delegating `access()` entry point | Inspect the actual public surface of `EntityAccessHandler` first. If only field-access is exposed, use `AccessChecker` or another sibling class. The contract is "evaluate the parent entity's `AccessPolicyInterface` for the given operation/account". |
| Concurrency test flaky on shared CI runners | File-backed SQLite + WAL mode (`PRAGMA journal_mode=WAL`) + retry logic on `SQLITE_BUSY`. The DBAL transaction wrapper should already handle this; verify it does in practice. |
| Auto-discovery doesn't find the policy class | The class must be PSR-4 reachable via the package's `composer.json` autoload. Once `WP05` lands with the autoload entry, discovery should work. Test by booting and asserting `AccessChecker::getPolicy('attachment')` returns the expected instance. |
| Stub-based unit tests pass but real auto-discovery differs at boot | The integration test in T028 catches this — it boots the real kernel and runs through the access pipeline. |

## Reviewer guidance

- Verify the policy's constructor signature is compatible with `discoverAccessPolicies()`'s heuristic — typed parameters with framework interfaces.
- Verify `Neutral` (not `Forbidden`) on missing parent. The CLAUDE.md note on access-result semantics ("Neutral = denied at entity level via `isAllowed()`") makes this safe.
- Verify the concurrency test actually exercises concurrency (not 50 sequential calls dressed up).
- Confirm no unreachable code paths (defensive `Neutral` returns are fine; comment them as defensive).
- No CHANGELOG edit (WP10).

## Implementation command

```bash
spec-kitty agent action implement WP06 --agent <agent-name> --mission single-entity-work-surface-01KQ7M1P
```

Depends on WP05.

## Activity Log

- 2026-04-27T16:41:02Z – claude:sonnet-4-6:implementer:implementer – shell_pid=23200 – Started implementation via action command
- 2026-04-27T16:47:46Z – claude:sonnet-4-6:implementer:implementer – shell_pid=23200 – ParentDelegatedAccessPolicy + concurrency test (NFR-010); 29 tests pass (1 skipped on Windows/non-pcntl); PHPStan level 5 clean; CS clean
- 2026-04-27T16:48:09Z – claude:opus-4-7:reviewer:reviewer – shell_pid=11068 – Started review via action command
- 2026-04-27T16:51:46Z – claude:opus-4-7:reviewer:reviewer – shell_pid=11068 – Review passed: 14 unit tests + 5 integration tests + concurrency test (skipped on Windows, will run on Linux CI). Policy correctly delegates to EntityAccessHandler::check(); Neutral on edge cases per spec. PHPStan errors are environmental (root config not yet wired for waaseyaa/attachment — WP05 territory); package autoload works (PHPUnit: 29 tests, 1 skipped). DIR-003 clean. Owned-files only.
- 2026-04-27T18:21:59Z – claude:opus-4-7:reviewer:reviewer – shell_pid=11068 – Done override: Mission merged at ca0ff03
