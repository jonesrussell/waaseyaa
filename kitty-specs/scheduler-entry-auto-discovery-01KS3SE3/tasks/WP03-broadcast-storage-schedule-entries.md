---
work_package_id: WP03
title: BroadcastStorageScheduleEntries
dependencies:
- WP02
requirement_refs:
- C-002
- FR-006
- NFR-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T014
- T015
- T016
- T017
agent: "claude:sonnet:implementer:implementer"
shell_pid: "806939"
history:
- date: '2026-05-20T23:57:21Z'
  event: created
authoritative_surface: packages/api/src/Schedule/
execution_mode: code_change
owned_files:
- packages/api/src/Schedule/BroadcastStorageScheduleEntries.php
- packages/api/src/Controller/BroadcastStorage.php
- packages/api/tests/Unit/Schedule/BroadcastStorageScheduleEntriesTest.php
- docs/specs/broadcasting.md
tags: []
---

# WP03 — `BroadcastStorageScheduleEntries`

## Objective

Implement the scheduled prune task for `_broadcast_log`, which has been growing unbounded since 2026-03 (evidence: 243 rows in Minoo's local DB). Create `BroadcastStorageScheduleEntries` implementing `ScheduleEntriesInterface`, verify/add `BroadcastStorage::prune()`, document in the broadcasting spec. Closes #1536.

**Requirement coverage**: FR-006, NFR-003, C-002, SC-002

## Context

### The problem (#1536)

`BroadcastStorage::push()` writes a row to `_broadcast_log` on every entity save/delete and every SSE poll. `BroadcastStorage::prune()` may exist but is never called on a schedule. Result: unbounded table growth on every consumer install.

After #1535 fixed history replay, the table still grows. This WP adds the prune schedule.

### Package layout

- `BroadcastStorage` lives at `packages/api/src/Controller/BroadcastStorage.php` (confirmed in plan).
- `BroadcastStorageScheduleEntries` goes in `packages/api/src/Schedule/BroadcastStorageScheduleEntries.php` — a new `Schedule/` subdirectory under `packages/api/src/`.
- `ScheduleEntriesInterface` is in `packages/scheduler/` (L0). `packages/api/` is L4. An L4 class implementing an L0 interface is a **downward** dependency — valid per layer architecture.

### Cron schedule decision (NFR-003)

- **Cron expression**: `0 2 * * *` (nightly at 02:00 UTC)
- **Rationale**: Off-peak, distinct from `AgentScheduleEntries`'s AI purge slot (03:00)
- **Default retention**: 7 days (rows older than 7 days are deleted)
- **Config key**: `schedule.broadcast_log_retention_days` (integer, default `7`)

### `BroadcastStorage::prune()` — verify before assuming

Open `packages/api/src/Controller/BroadcastStorage.php` and check whether `prune(int $retentionDays = 7): void` exists. It was referenced in the spec but may not have been implemented yet. If it exists, verify the DELETE logic. If absent, add it.

Expected implementation:
```php
public function prune(int $retentionDays = 7): void
{
    $cutoff = (new \DateTimeImmutable())->modify("-{$retentionDays} days");
    $this->database->delete('_broadcast_log', [
        ['created_at', '<', $cutoff->format('Y-m-d H:i:s')],
    ]);
}
```

Adapt to the `DatabaseInterface` query builder pattern in use (check existing `BroadcastStorage` methods for the query style).

## Branch Strategy

- **Planning/base branch**: `main`
- **Merge target**: `main`
- **Depends on**: WP02 (kernel wiring and interface must exist)
- **Parallel with**: WP04 — both can run after WP02 merges
- **Execution**: `spec-kitty agent action implement WP03 --agent <name>`

## Subtask Guidance

### T014 — Verify/add `BroadcastStorage::prune(int $retentionDays)`

**Purpose**: Ensure the prune operation exists before building the schedule entry around it.

**File**: `packages/api/src/Controller/BroadcastStorage.php` (edit if needed)

**Steps**:
1. Open `BroadcastStorage.php` and search for `prune`.
2. If `prune()` exists:
   - Verify signature: `prune(int $retentionDays = 7): void`
   - Verify it deletes rows where `created_at < now() - retentionDays`
   - If signature differs, align it to this shape
3. If `prune()` does not exist, add it:
   ```php
   public function prune(int $retentionDays = 7): void
   {
       $cutoff = (new \DateTimeImmutable())->modify("-{$retentionDays} days");
       $this->database->delete('_broadcast_log', [
           ['created_at', '<', $cutoff->format('Y-m-d H:i:s')],
       ]);
   }
   ```
4. Check the `_broadcast_log` table schema to confirm `created_at` column exists with a datetime/timestamp type.
5. Check that `DatabaseInterface::delete()` accepts a conditions array in this form — adapt if the query builder syntax differs.

**Important**: Do NOT use raw PDO or string SQL. Use `DatabaseInterface`'s query builder (see CLAUDE.md Architecture Gotchas: "DatabaseInterface vs DBALDatabase"). If `delete()` requires a different condition format, check existing usages in `BroadcastStorage` or other storage classes.

**Validation**:
- `prune()` method exists with `int $retentionDays = 7` default
- Uses `DatabaseInterface` query builder, not raw SQL
- `declare(strict_types=1)` present in file

---

### T015 — Create `BroadcastStorageScheduleEntries`

**Purpose**: Implement the schedule entry that registers the nightly prune task.

**File**: `packages/api/src/Schedule/BroadcastStorageScheduleEntries.php` (new)

**Create the `Schedule/` subdirectory** if it doesn't exist (check `ls packages/api/src/` first).

**Implementation**:
```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Schedule;

use Waaseyaa\Api\Controller\BroadcastStorage;
use Waaseyaa\Scheduler\ScheduleEntriesInterface;
use Waaseyaa\Scheduler\ScheduleInterface;
use Waaseyaa\Scheduler\ScheduledTask;

/**
 * Registers the nightly _broadcast_log prune task.
 *
 * Default schedule: 0 2 * * * (02:00 UTC daily)
 * Default retention: 7 days (configurable via schedule.broadcast_log_retention_days)
 *
 * To disable: add Waaseyaa\Api\Schedule\BroadcastStorageScheduleEntries to
 * schedule.disabled_entries in your configuration.
 *
 * @api
 */
final class BroadcastStorageScheduleEntries implements ScheduleEntriesInterface
{
    private int $retentionDays;

    public function __construct(
        private readonly BroadcastStorage $broadcastStorage,
        array $config = [],
    ) {
        $this->retentionDays = (int) ($config['schedule']['broadcast_log_retention_days'] ?? 7);
    }

    public function register(ScheduleInterface $schedule): array
    {
        $retentionDays = $this->retentionDays;
        $broadcastStorage = $this->broadcastStorage;

        $pruneTask = $schedule->add(
            name: 'broadcast_log_prune',
            cron: '0 2 * * *',
            callback: static function () use ($broadcastStorage, $retentionDays): void {
                $broadcastStorage->prune($retentionDays);
            },
        );

        return ['prune' => $pruneTask];
    }
}
```

**Adaptation notes**:
- Check `ScheduleInterface::add()` signature — adapt named argument names to match the actual method signature. Look at how `AgentScheduleEntries::register()` calls `$schedule->add()` for the exact call shape.
- The `$config` constructor parameter with default `[]` means the container can inject it as a scalar array (check how other schedule-entries classes or service providers pass config). If the kernel's resolver skips scalar defaults, `$config` defaults to `[]` and `$retentionDays` defaults to `7` — this is fine.
- If `ScheduleInterface::add()` returns a different type than `ScheduledTask` (e.g. a builder), adapt the return assignment.

**Validation**:
- `implements ScheduleEntriesInterface` present
- `@api` in docblock (dead-code gate requirement)
- Returns `['prune' => $pruneTask]` — task identity key `prune`
- Cron `0 2 * * *` — documented in class docblock and in T017's spec update
- `declare(strict_types=1)` present; `final class`
- Layer check: L4 class imports L0 interface (downward — valid)

---

### T016 — Unit test: prune task registers with correct cron + calls `prune()`

**Purpose**: Verify the schedule entry registers a task with the expected cron expression and that the prune closure calls `BroadcastStorage::prune()`.

**File**: `packages/api/tests/Unit/Schedule/BroadcastStorageScheduleEntriesTest.php` (new)

**Implementation**:
```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Tests\Unit\Schedule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Api\Controller\BroadcastStorage;
use Waaseyaa\Api\Schedule\BroadcastStorageScheduleEntries;
use Waaseyaa\Scheduler\ScheduleInterface;
use Waaseyaa\Scheduler\ScheduledTask;

#[CoversClass(BroadcastStorageScheduleEntries::class)]
final class BroadcastStorageScheduleEntriesTest extends TestCase
{
    #[Test]
    public function registerAddsPruneTaskWithNightlyCron(): void
    {
        $broadcastStorage = $this->createMock(BroadcastStorage::class);
        $schedule = $this->createMock(ScheduleInterface::class);

        $task = $this->createMock(ScheduledTask::class);

        $schedule->expects(self::once())
            ->method('add')
            ->with(
                name: 'broadcast_log_prune',
                cron: '0 2 * * *',
                self::anything(), // callback
            )
            ->willReturn($task);

        $entries = new BroadcastStorageScheduleEntries($broadcastStorage);
        $result = $entries->register($schedule);

        self::assertArrayHasKey('prune', $result);
        self::assertSame($task, $result['prune']);
    }

    #[Test]
    public function pruneCallbackInvokesStoragePrune(): void
    {
        $broadcastStorage = $this->createMock(BroadcastStorage::class);
        $broadcastStorage->expects(self::once())
            ->method('prune')
            ->with(7); // default retention days

        $capturedCallback = null;
        $schedule = $this->createMock(ScheduleInterface::class);
        $schedule->method('add')
            ->willReturnCallback(function (string $name, string $cron, callable $callback) use (&$capturedCallback) {
                $capturedCallback = $callback;
                return $this->createMock(ScheduledTask::class);
            });

        $entries = new BroadcastStorageScheduleEntries($broadcastStorage);
        $entries->register($schedule);

        self::assertNotNull($capturedCallback);
        ($capturedCallback)(); // invoke the scheduled closure
    }

    #[Test]
    public function customRetentionDaysPassedToConfig(): void
    {
        $broadcastStorage = $this->createMock(BroadcastStorage::class);
        $broadcastStorage->expects(self::once())
            ->method('prune')
            ->with(14);

        $capturedCallback = null;
        $schedule = $this->createMock(ScheduleInterface::class);
        $schedule->method('add')
            ->willReturnCallback(function (string $name, string $cron, callable $callback) use (&$capturedCallback) {
                $capturedCallback = $callback;
                return $this->createMock(ScheduledTask::class);
            });

        $config = ['schedule' => ['broadcast_log_retention_days' => 14]];
        $entries = new BroadcastStorageScheduleEntries($broadcastStorage, $config);
        $entries->register($schedule);
        ($capturedCallback)();
    }
}
```

**Adaptation notes**:
- Adapt `ScheduleInterface::add()` mock call to match the actual method signature
- If `BroadcastStorage` is `final`, use `createStub()` instead of `createMock()`
- Adjust named args to match actual `add()` parameter names

**Validation**:
- All three tests pass
- `#[CoversClass(BroadcastStorageScheduleEntries::class)]` present

---

### T017 — Update `docs/specs/broadcasting.md`

**Purpose**: Document the scheduled pruning behavior so operators and consumers know about the nightly prune, the default retention, and the opt-out.

**File**: `docs/specs/broadcasting.md` (edit)

**Add a new section** (search for an appropriate insertion point, likely after the "BroadcastStorage" section):

```markdown
## Scheduled Pruning

`_broadcast_log` is pruned automatically by `BroadcastStorageScheduleEntries`, which is
auto-discovered at kernel boot via `ScheduleEntriesInterface`.

### Default schedule

| Property | Value |
|---|---|
| Cron | `0 2 * * *` (02:00 UTC nightly) |
| Retention window | 7 days (rows older than 7 days are deleted) |
| Config key | `schedule.broadcast_log_retention_days` (integer) |
| Task identity | `broadcast_log_prune` |

### Customizing retention

Set `schedule.broadcast_log_retention_days` in your configuration:

```yaml
schedule:
  broadcast_log_retention_days: 14  # keep 14 days of broadcast log history
```

### Disabling the prune task

Add the class FQCN to `schedule.disabled_entries`:

```yaml
schedule:
  disabled_entries:
    - Waaseyaa\Api\Schedule\BroadcastStorageScheduleEntries
```

When disabled, the entry appears as `[disabled]` in `bin/waaseyaa schedule:list` output
and `prune()` is never called — the table grows without bound. Disable only if you manage
pruning externally (e.g. via a custom database maintenance job).

### Background

Issue #1536 documented 243 rows accumulating in Minoo's local DB from 2026-03 testing.
The fix (`BroadcastStorageScheduleEntries`, M-D mission) adds auto-discovered pruning
so consumers never need to wire the prune task manually.
```

**Validation**:
- Section is present in `docs/specs/broadcasting.md`
- Cron expression, retention default, and config key are documented
- Opt-out instructions are present
- No markdown formatting errors

## Definition of Done

- [ ] `BroadcastStorage::prune(int $retentionDays = 7)` exists and uses `DatabaseInterface` query builder
- [ ] `packages/api/src/Schedule/BroadcastStorageScheduleEntries.php` exists with `@api`, correct cron, correct return
- [ ] Unit tests pass: `registerAddsPruneTaskWithNightlyCron`, `pruneCallbackInvokesStoragePrune`, `customRetentionDaysPassedToConfig`
- [ ] `docs/specs/broadcasting.md` updated with "Scheduled Pruning" section
- [ ] `bin/check-package-layers` passes (L4 → L0 downward dependency valid)
- [ ] `composer verify` green

## Risks

| Risk | Mitigation |
|---|---|
| `BroadcastStorage` is `final` — `createMock()` fails | Use `createStub()` or real instance in test |
| `_broadcast_log` schema missing `created_at` | Check migration; add column if absent (out of scope for this WP — file follow-up issue if needed) |
| `ScheduleInterface::add()` signature differs from assumption | Check `AgentScheduleEntries::register()` for actual call shape before writing the test mock |
| L4 → L0 package import breaks layer check | Verify `packages/api/composer.json` `require` includes `waaseyaa/scheduler` or the appropriate dependency |

## Reviewer Guidance

- Verify cron `0 2 * * *` is correct (not `0 3 * * *` which is the AI purge slot)
- Confirm `prune()` deletes rows older than retention days (not newer)
- Verify no raw PDO / SQL in `BroadcastStorage::prune()`
- Check `@api` presence on `BroadcastStorageScheduleEntries` (dead-code gate)
- Verify the `Schedule/` directory is included in `packages/api/composer.json` PSR-4 autoload (the namespace `Waaseyaa\Api\` should already cover `src/`)

## Activity Log

- 2026-05-21T01:06:21Z – unknown – BroadcastStorageScheduleEntries + PackageManifestCompiler regression fix; 30/30 manifest tests pass, 3/3 new unit tests pass, phpstan clean, 1385 total tests green
- 2026-05-21T01:07:18Z – claude:opus-4-7:reviewer:reviewer – shell_pid=796050 – Started review via action command
- 2026-05-21T01:11:13Z – claude:opus-4-7:reviewer:reviewer – shell_pid=796050 – Moved to planned
- 2026-05-21T01:12:34Z – claude:sonnet:implementer:implementer – shell_pid=806939 – Started implementation via action command
- 2026-05-21T01:17:05Z – claude:sonnet:implementer:implementer – shell_pid=806939 – Cycle 1: surface-map updated (public disposition) + BroadcastStorage dep made nullable; 13 regressions cleared (8 SSR + 4 OIDC + 1 SurfaceMap). 1386/1386 pkg tests + 12/12 SSR+OIDC + 3/3 SurfaceMap.
