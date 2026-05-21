# Contract: ScheduleEntriesInterface

**FQCN**: `Waaseyaa\Scheduler\ScheduleEntriesInterface`
**Layer**: L0 (`packages/scheduler/`)
**File**: `packages/scheduler/src/ScheduleEntriesInterface.php`
**WP**: WP01
**Stability**: `@api`

---

## Interface Definition

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Scheduler;

/**
 * Contract for auto-discoverable recurring schedule entries.
 *
 * Implement this interface in any package to register recurring tasks with
 * the Waaseyaa scheduler. The PackageManifestCompiler discovers all
 * implementors at manifest compile time; AbstractKernel resolves and calls
 * register() at boot. No ServiceProvider::boot() wiring required.
 *
 * Constructor dependencies are resolved via the container-resolved resolver
 * protocol (PolicyDependencyResolverInterface). Any unresolvable dependency
 * causes kernel boot to fail with a ScheduleEntryInstantiationException
 * naming the class and the missing dependency.
 *
 * To opt out of a built-in entries class, add its FQCN to the
 * schedule.disabled_entries configuration key.
 *
 * @api
 */
interface ScheduleEntriesInterface
{
    /**
     * Register recurring tasks on the supplied schedule.
     *
     * Called once per kernel boot for each discovered implementor.
     * Implementations may call $schedule->add() one or more times.
     *
     * @return array<string, ScheduledTask> Tasks keyed by task identity string.
     */
    public function register(ScheduleInterface $schedule): array;
}
```

---

## Discovery mechanism

`PackageManifestCompiler` uses interface-implementation detection:

```php
private const SCHEDULE_ENTRIES_INTERFACE = 'Waaseyaa\\Scheduler\\ScheduleEntriesInterface';

// In filterDiscoveryClasses():
$implements = class_implements($class);
if (is_array($implements) && isset($implements[self::SCHEDULE_ENTRIES_INTERFACE])) {
    $scheduleEntries[] = $class;
}
```

String constant avoids upward layer imports (Foundation/L0 must not import from Scheduler/L0's sibling classes at scan time).

---

## Manifest key

`PackageManifest::$scheduleEntries` (`list<class-string>`)  
Cache file key: `schedule_entries`

---

## Resolver adoption note

`ScheduleEntryRegistry` uses `Waaseyaa\Foundation\Kernel\Bootstrap\PolicyDependencyResolverInterface`
(M-B's resolver, `@api`-marked for M-D reuse). If M-B has not landed, a
structurally identical `ScheduleEntryDependencyResolverInterface` is introduced
in the same namespace, pending consolidation.

---

## Built-in implementors

| Class | Package | Tasks |
|---|---|---|
| `Waaseyaa\Scheduler\Schedule\Ai\AgentScheduleEntries` | `packages/scheduler` | `ai:purge-runs` (daily 03:00 UTC), `ai:reap-stalled-runs` (every 5 min) |
| `Waaseyaa\Api\Schedule\BroadcastStorageScheduleEntries` | `packages/api` | `_broadcast_log:prune` (nightly 02:00 UTC, 7-day retention) |
