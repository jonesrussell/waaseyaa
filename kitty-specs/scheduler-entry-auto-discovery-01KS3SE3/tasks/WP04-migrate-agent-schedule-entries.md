---
work_package_id: WP04
title: Migrate AgentScheduleEntries
dependencies:
- WP02
requirement_refs:
- FR-005
- FR-012
- FR-013
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T018
- T019
- T020
- T021
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "823012"
history:
- date: '2026-05-20T23:57:21Z'
  event: created
authoritative_surface: packages/scheduler/src/Schedule/
execution_mode: code_change
owned_files:
- packages/scheduler/src/Schedule/Ai/AgentScheduleEntries.php
- tests/Integration/Phase13/ScheduleEntryAutoDiscoveryTest.php
tags: []
---

# WP04 — Migrate `AgentScheduleEntries`

## Objective

Add `implements ScheduleEntriesInterface` to `AgentScheduleEntries` (one-line change — signature already matches). Verify no orphaned manual wiring exists. Write the integration tests that prove end-to-end auto-discovery of all built-in schedule entries. Closes #1512.

**Requirement coverage**: FR-005, FR-012, FR-013, SC-001, SC-002, SC-005

## Context

### Why #1512 is open

`AgentScheduleEntries` declares `ai:purge-runs` (daily) and `ai:reap-stalled-runs` (every 5 minutes), but nothing calls `register()` on it. The class carries `@api` (keeps the dead-code detector quiet) but has no implementation path from boot. The agent runtime's retention sweep and crash-recovery reaper are silently inert in production.

After WP01–WP02 land, the auto-discovery infrastructure is live. This WP wires `AgentScheduleEntries` into it with a single `implements` addition.

### `AgentScheduleEntries` — existing shape

From plan (confirmed in spec):
- Location: `packages/scheduler/src/Schedule/Ai/AgentScheduleEntries.php`
- Line 68: `register(ScheduleInterface $schedule): array` — already matches `ScheduleEntriesInterface` contract
- Returns: `array{purge: ScheduledTask, reap: ScheduledTask}` — matches `array<string, ScheduledTask>`
- `@api` annotation: present — no change needed
- Constructor: uses closure-invoker pattern for CLI command dispatch (L0 → L6 layer-safe)

The only code change needed is adding `implements ScheduleEntriesInterface` to the class declaration.

### Integration phase verification

The plan targets `tests/Integration/Phase13/`. **Before creating the directory**, verify the next unused phase:

```bash
ls tests/Integration/ | sort
```

If Phase13 is already taken, use the next available number. Update the file path accordingly.

### Integration test scope

`ScheduleEntryAutoDiscoveryTest` must cover:
1. **`listsBuiltInTasks`** (FR-012, SC-001, SC-005): Boot the kernel, invoke `schedule:list` (or directly query the registered schedule), assert both AgentScheduleEntries tasks (`ai:purge-runs`, `ai:reap-stalled-runs`) and BroadcastStorageScheduleEntries' task (`broadcast_log_prune`) appear with correct cron expressions.

2. **`pruneTaskRemovesOldRows`** (FR-013, SC-002): Insert old `_broadcast_log` rows into an in-memory SQLite DB, run the prune task closure directly, assert rows older than 7 days are deleted and rows within 7 days are preserved.

### Kernel boot for integration tests

Integration tests in `tests/Integration/PhaseN/` boot the kernel with `DBALDatabase::createSqlite(':memory:')`. Check Phase12 or Phase11 for the existing boot pattern — follow it exactly.

## Branch Strategy

- **Planning/base branch**: `main`
- **Merge target**: `main`
- **Depends on**: WP02 (kernel wiring active); WP03 recommended to merge first so `listsBuiltInTasks` can assert all three built-in tasks, but WP04 can be written in parallel with WP03 if the integration test conditionally skips `broadcast_log_prune` assertion until WP03 lands
- **Parallel with**: WP03
- **Execution**: `spec-kitty agent action implement WP04 --agent <name>`

## Subtask Guidance

### T018 — Add `implements ScheduleEntriesInterface` to `AgentScheduleEntries`

**Purpose**: Make `AgentScheduleEntries` discoverable by `PackageManifestCompiler`.

**File**: `packages/scheduler/src/Schedule/Ai/AgentScheduleEntries.php` (edit)

**Steps**:
1. Open the file.
2. Add the `ScheduleEntriesInterface` import:
   ```php
   use Waaseyaa\Scheduler\ScheduleEntriesInterface;
   ```
3. Add `implements ScheduleEntriesInterface` to the class declaration:
   ```php
   final class AgentScheduleEntries implements ScheduleEntriesInterface
   ```
4. Verify `register()` signature matches the interface exactly:
   - `public function register(ScheduleInterface $schedule): array`
   - Return type annotation `@return array<string, ScheduledTask>` matches

**Validation**:
- Class declaration includes `implements ScheduleEntriesInterface`
- No signature changes to `register()` — it already matches
- `@api` annotation unchanged
- `declare(strict_types=1)` present
- `composer verify` runs clean (PHPStan confirms the interface is satisfied)

---

### T019 — Verify no orphaned manual `register()` wiring

**Purpose**: Confirm no `ServiceProvider::boot()` was ever wired to call `AgentScheduleEntries::register()` manually. If any exists, remove it (it would double-register the tasks).

**Steps**:
1. Run:
   ```bash
   grep -r "AgentScheduleEntries" packages/*/src/ --include="*.php" -l
   ```
2. For each file found, check if it calls `->register()` on `AgentScheduleEntries`.
3. Also search:
   ```bash
   grep -r "AgentScheduleEntries" packages/*/src/ --include="*.php"
   ```
4. Expected findings: zero `ServiceProvider` files calling `register()` (that's why #1512 is open — the wiring was forgotten). If any such call exists, remove it; add a comment noting it was replaced by auto-discovery.
5. Document the search result in your commit message (e.g. "Verified: no manual register() wiring found").

**Validation**:
- No `ServiceProvider` calls `AgentScheduleEntries->register()`
- Any removed wiring is noted in commit message

---

### T020 — Integration test `listsBuiltInTasks` (FR-012, SC-001, SC-005)

**Purpose**: End-to-end proof that auto-discovery works: a fresh kernel boot with no consumer wiring produces all expected built-in tasks.

**File**: `tests/Integration/Phase13/ScheduleEntryAutoDiscoveryTest.php` (new — verify phase number first)

**Prerequisite**: Verify phase number:
```bash
ls /home/jones/dev/waaseyaa/tests/Integration/ | sort
```
Use next available phase. If Phase13 exists, use Phase14, etc.

**Implementation approach**:
```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Phase13;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
// Import kernel and schedule dependencies per existing integration test pattern

#[CoversNothing]
final class ScheduleEntryAutoDiscoveryTest extends TestCase
{
    #[Test]
    public function listsBuiltInTasks(): void
    {
        // 1. Boot kernel with in-memory SQLite (follow existing Phase integration pattern)
        $kernel = ...; // construct using existing test infrastructure
        $kernel->boot();

        // 2. Get the registered schedule
        $schedule = $kernel->getSchedule(); // or however schedule is accessed
        $tasks = $schedule->tasks(); // returns array<string, ScheduledTask> or similar

        // 3. Assert AgentScheduleEntries tasks present (SC-005)
        self::assertArrayHasKey('purge', $tasks);
        self::assertSame('0 0 * * *', $tasks['purge']->getCron()); // daily — verify exact cron from AgentScheduleEntries

        self::assertArrayHasKey('reap', $tasks);
        self::assertSame('*/5 * * * *', $tasks['reap']->getCron()); // every 5 min — verify exact cron

        // 4. Assert BroadcastStorageScheduleEntries task present (after WP03 merges)
        self::assertArrayHasKey('broadcast_log_prune', $tasks);
        self::assertSame('0 2 * * *', $tasks['broadcast_log_prune']->getCron());
    }
}
```

**Critical**: Look at `AgentScheduleEntries::register()` to get the exact cron expressions for `purge` and `reap`. The plan says "daily" and "every five minutes" — verify the exact cron strings.

Look at existing integration tests (e.g. `tests/Integration/Phase12/`) for the kernel boot pattern. Copy it exactly:
- How is `DBALDatabase::createSqlite(':memory:')` wired?
- How is the kernel constructed?
- How are schema/migrations run?
- How is the schedule accessed post-boot?

**Validation**:
- Test boots without errors
- All three built-in tasks appear (if WP03 merged) or two tasks with a note that WP03 adds the third
- Cron expressions match exactly what the schedule entries register

---

### T021 — Integration test `pruneTaskRemovesOldRows` (FR-013, SC-002)

**Purpose**: Prove that `BroadcastStorageScheduleEntries`'s prune task actually deletes old rows (not just that it registers).

**File**: `tests/Integration/Phase13/ScheduleEntryAutoDiscoveryTest.php` (add method to same file as T020)

**Implementation approach**:
```php
#[Test]
public function pruneTaskRemovesOldRows(): void
{
    // 1. Set up in-memory SQLite with _broadcast_log table
    $database = DBALDatabase::createSqlite(':memory:');
    // Create _broadcast_log table (check BroadcastStorage for schema or find migration)
    $database->execute(
        "CREATE TABLE _broadcast_log (id INTEGER PRIMARY KEY, payload TEXT, created_at DATETIME)"
    );

    // 2. Insert rows: some old (>7 days), some recent (<7 days)
    $oldDate = (new \DateTimeImmutable())->modify('-10 days')->format('Y-m-d H:i:s');
    $recentDate = (new \DateTimeImmutable())->modify('-3 days')->format('Y-m-d H:i:s');

    $database->insert('_broadcast_log', ['payload' => 'old1', 'created_at' => $oldDate]);
    $database->insert('_broadcast_log', ['payload' => 'old2', 'created_at' => $oldDate]);
    $database->insert('_broadcast_log', ['payload' => 'recent1', 'created_at' => $recentDate]);

    // 3. Instantiate BroadcastStorage and BroadcastStorageScheduleEntries
    $broadcastStorage = new BroadcastStorage($database);
    $entries = new BroadcastStorageScheduleEntries($broadcastStorage);

    // 4. Register tasks on a real Schedule instance
    $schedule = ...; // use real Schedule or test double that captures closures
    $tasks = $entries->register($schedule);

    // 5. Invoke the prune closure
    // (Assuming schedule captures closures and they can be invoked in test)
    $broadcastStorage->prune(7); // invoke directly if schedule extraction is complex

    // 6. Assert old rows deleted, recent rows preserved
    $rows = $database->select('_broadcast_log')->fetchAllAssociative();
    self::assertCount(1, $rows);
    self::assertSame('recent1', $rows[0]['payload']);
}
```

**Adaptation notes**:
- If extracting the closure from the schedule is complex, invoke `$broadcastStorage->prune(7)` directly to test the delete behavior. The schedule registration is covered in T016's unit test.
- Check `BroadcastStorage` constructor — it may require more than just `$database`.
- Find the `_broadcast_log` schema in the broadcasting migration or in `BroadcastStorage` itself.

**Validation**:
- Old rows (>7 days) deleted
- Recent rows (<7 days) preserved
- Test passes with real SQLite (not mocked)

## Definition of Done

- [ ] `AgentScheduleEntries` class declaration includes `implements ScheduleEntriesInterface`
- [ ] grep confirms no orphaned manual `register()` wiring in any ServiceProvider
- [ ] Integration test `listsBuiltInTasks` passes — `ai:purge-runs` and `ai:reap-stalled-runs` appear
- [ ] Integration test `pruneTaskRemovesOldRows` passes — old rows deleted, recent rows kept
- [ ] Integration phase directory confirmed (not assumed to be Phase13)
- [ ] `#[CoversNothing]` on the integration test class
- [ ] `composer verify` green

## Risks

| Risk | Mitigation |
|---|---|
| Integration phase13 already exists | Run `ls tests/Integration/` before creating directory |
| Kernel boot pattern differs between phases | Copy exactly from most recent Phase integration test |
| `schedule->tasks()` API doesn't exist | Check `ScheduleInterface` for task enumeration method; may need to access via `ScheduleListHandler` instead |
| `_broadcast_log` schema not available for in-memory test | Find schema in BroadcastStorage or migration; create table inline |
| WP03 not merged when WP04 runs | Skip `broadcast_log_prune` assertion in T020 with a TODO comment; add assertion after WP03 merges |

## Reviewer Guidance

- Verify the exact cron strings match what `AgentScheduleEntries` actually registers (not guesses from spec)
- Confirm integration test uses `DBALDatabase::createSqlite(':memory:')` not a real SQLite file
- Verify `#[CoversNothing]` on integration test (not `#[CoversClass]`)
- Check that `pruneTaskRemovesOldRows` actually verifies database state (not just that prune was called)
- Verify `Closes #1512` appears in the PR description / merge commit

## Activity Log

- 2026-05-21T01:23:08Z – unknown – AgentScheduleEntries auto-discovered via ScheduleEntriesInterface; Phase30 integration test asserts schedule:list tasks and prune behavior (FR-005, FR-012, FR-013)
- 2026-05-21T01:23:48Z – claude:opus-4-7:reviewer:reviewer – shell_pid=823012 – Started review via action command
