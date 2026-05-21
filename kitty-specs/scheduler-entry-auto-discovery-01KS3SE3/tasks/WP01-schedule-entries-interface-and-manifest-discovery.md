---
work_package_id: WP01
title: ScheduleEntriesInterface + Manifest Discovery
dependencies: []
requirement_refs:
- C-001
- C-006
- FR-001
- FR-002
- FR-009
- NFR-001
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-scheduler-entry-auto-discovery-01KS3SE3
base_commit: a1763ce48f205a0f2227040f0c215e109bfba246
created_at: '2026-05-21T00:25:49.308976+00:00'
subtasks:
- T001
- T002
- T003
- T004
- T005
- T006
shell_pid: "705854"
agent: "claude:sonnet:implementer:implementer"
history:
- date: '2026-05-20T23:57:21Z'
  event: created
authoritative_surface: packages/scheduler/src/
execution_mode: code_change
owned_files:
- packages/scheduler/src/ScheduleEntriesInterface.php
- packages/scheduler/tests/Unit/ScheduleEntriesInterfaceTest.php
- packages/foundation/src/Discovery/PackageManifestCompiler.php
- packages/foundation/src/Discovery/PackageManifest.php
- packages/foundation/tests/Unit/Discovery/PackageManifestCompilerTest.php
tags: []
---

# WP01 — `ScheduleEntriesInterface` + Manifest Discovery

## Objective

Define the L0 `ScheduleEntriesInterface` contract and extend `PackageManifestCompiler` / `PackageManifest` to discover all implementors at compile time. This is the foundation all other WPs depend on — nothing else can land until the interface exists and the manifest knows how to record it.

**Requirement coverage**: FR-001, FR-002, FR-009, NFR-001, C-001, C-006

## Context

### Why this interface exists

`packages/scheduler/src/Schedule/Ai/AgentScheduleEntries.php` already has a `register(ScheduleInterface $schedule): array` method, but it is not discoverable — nothing auto-wires it. The gap: Waaseyaa's `PackageManifestCompiler` already discovers middleware (`#[AsMiddleware]`), access policies (`#[PolicyAttribute]`), service providers (`extra.waaseyaa.providers`), and ingestion mappers. Schedule entries are the only extension point not covered.

This WP adds `ScheduleEntriesInterface` (the contract) and teaches the compiler to discover it via interface scanning — matching the existing `CAPABILITY_HAS_NATIVE_COMMANDS` pattern.

### Relevant existing code

**`packages/foundation/src/Discovery/PackageManifestCompiler.php`**
- `filterDiscoveryClasses()` — gates which classes are recorded. Currently checks attributes. Must also check `class_implements()` for `ScheduleEntriesInterface`.
- Pattern already in use:
  ```php
  private const CAPABILITY_HAS_NATIVE_COMMANDS = 'Waaseyaa\\Foundation\\ServiceProvider\\Capability\\HasNativeCommandsInterface';
  // ...
  $implements = class_implements($providerClass);
  if (is_array($implements) && isset($implements[self::CAPABILITY_HAS_NATIVE_COMMANDS])) { ... }
  ```
- Use string constant (not `::class`) to preserve layer discipline (Foundation must not import from Scheduler).

**`packages/foundation/src/Discovery/PackageManifest.php`**
- Immutable DTO. Check whether it has `fromArray()` and `toArray()` methods or uses a constructor-only pattern. Adapt accordingly.
- Existing fields follow `snake_case` manifest keys: `field_types`, `agent_tools`, etc. New key: `schedule_entries`.

**`packages/scheduler/src/Schedule/Ai/AgentScheduleEntries.php`**
- `register(ScheduleInterface $schedule): array` at line 68 — this is the shape to formalize in the interface.
- Return type is `array<string, ScheduledTask>` (named tasks: `['purge' => $task, 'reap' => $task]`).

**`packages/scheduler/src/ScheduleInterface.php`**
- L0. Minimal contract: `tasks()`, `add()`. The new interface's `register()` accepts `ScheduleInterface` — no change needed to `ScheduleInterface`.

**`packages/scheduler/src/ScheduledTask.php`**
- L0. Value object returned from `register()`. No change needed.

### Layer discipline

`ScheduleEntriesInterface` lives in `packages/scheduler/` (L0). `PackageManifestCompiler` is in `packages/foundation/` (L0). L0 → L0 is valid. The compiler must reference the interface by string constant, not `::class`, to avoid a circular dependency within L0 (foundation importing from scheduler).

`bin/check-package-layers` must pass after this WP.

## Branch Strategy

- **Planning/base branch**: `main`
- **Merge target**: `main`
- **Execution**: `spec-kitty agent action implement WP01 --agent <name>` allocates a worktree from `lanes.json`.

No dependencies — this WP can begin immediately.

## Subtask Guidance

### T001 — Define `ScheduleEntriesInterface`

**Purpose**: Introduce the L0 interface that makes schedule entries discoverable and gives `AgentScheduleEntries` / `BroadcastStorageScheduleEntries` a common contract.

**File**: `packages/scheduler/src/ScheduleEntriesInterface.php` (new)

**Implementation**:
```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Scheduler;

/**
 * Contract for discoverable schedule entries.
 *
 * Implementations are auto-discovered by PackageManifestCompiler and
 * registered at kernel boot via ScheduleEntryRegistry. No ServiceProvider
 * wiring is required — implement this interface and ensure constructor
 * dependencies are container-resolvable.
 *
 * @api
 */
interface ScheduleEntriesInterface
{
    /**
     * Register recurring tasks on the supplied schedule.
     *
     * @return array<string, ScheduledTask> Keyed by task identity string.
     *                                      The key is used for introspection (e.g. schedule:list).
     */
    public function register(ScheduleInterface $schedule): array;
}
```

**Validation**:
- `declare(strict_types=1)` present
- `@api` in docblock (required by dead-code gate — prevents shipmonk from flagging as unused)
- Return type `array<string, ScheduledTask>` matches `AgentScheduleEntries::register()` existing shape
- No imports from Foundation or higher layers

---

### T002 — Add `$scheduleEntries` field to `PackageManifest`

**Purpose**: Record discovered FQCNs in the manifest DTO so the kernel can enumerate them at boot.

**File**: `packages/foundation/src/Discovery/PackageManifest.php` (edit)

**Steps**:
1. Open the file and locate the constructor signature.
2. Add `public readonly array $scheduleEntries = []` (type hint `list<class-string>` in docblock).
3. Find `fromArray()` (if present) — add: `scheduleEntries: $data['schedule_entries'] ?? []`
4. Find `toArray()` (if present) — add: `'schedule_entries' => $this->scheduleEntries`
5. If the manifest is constructed differently (e.g. static factory), adapt to match the existing pattern for other array fields.

**Validation**:
- `$manifest->scheduleEntries` is accessible and returns an array
- Manifest serializes/deserializes `schedule_entries` key symmetrically
- No `@dev` usage introduced (composer policy CP002)

---

### T003 — Extend `PackageManifestCompiler::filterDiscoveryClasses()`

**Purpose**: Teach the compiler to pass any class implementing `ScheduleEntriesInterface` through the discovery filter.

**File**: `packages/foundation/src/Discovery/PackageManifestCompiler.php` (edit)

**Steps**:
1. Add string constant near the top of the class (alongside other capability constants):
   ```php
   private const SCHEDULE_ENTRIES_INTERFACE = 'Waaseyaa\\Scheduler\\ScheduleEntriesInterface';
   ```
2. In `filterDiscoveryClasses()`, after the existing attribute checks, add:
   ```php
   // Discover ScheduleEntriesInterface implementors
   $implements = @class_implements($class);
   if (is_array($implements) && isset($implements[self::SCHEDULE_ENTRIES_INTERFACE])) {
       return true;
   }
   ```
3. Verify the method returns `bool` — the new branch must return `true` (pass through), not modify existing logic.

**Important**: Use `@class_implements()` (or try/catch) defensively — autoloading failures in PSR-4 scans must not abort the full compile. Check how the compiler handles autoload failures for other interface checks and match that pattern.

**Validation**:
- A class in `packages/scheduler/src/` implementing `ScheduleEntriesInterface` is included in discovery output
- A class in `packages/scheduler/src/` NOT implementing the interface is NOT included (unless it matches another criterion)
- Layer: Foundation string constant references Scheduler FQCN only as string literal — no `use` import of Scheduler types

---

### T004 — Extend `PackageManifestCompiler::compile()` to populate `schedule_entries`

**Purpose**: Collect all `ScheduleEntriesInterface` implementors found during scan and write them into the manifest.

**File**: `packages/foundation/src/Discovery/PackageManifestCompiler.php` (edit)

**Steps**:
1. In `compile()`, add a collection step for schedule entries (parallel to the existing collection steps for policies, middleware, etc.):
   ```php
   $scheduleEntries = [];
   foreach ($discoveredClasses as $class) {
       $implements = @class_implements($class);
       if (is_array($implements) && isset($implements[self::SCHEDULE_ENTRIES_INTERFACE])) {
           $scheduleEntries[] = $class;
       }
   }
   ```
2. Pass `$scheduleEntries` when constructing the `PackageManifest` DTO (or set via `withScheduleEntries()` if the DTO uses a builder pattern).

**Validation**:
- `$manifest->scheduleEntries` contains the FQCN of `AgentScheduleEntries` when compiled against `packages/scheduler/`
- Duplicates are not recorded (use `array_unique` if needed)
- Compile does not error on packages with no `ScheduleEntriesInterface` implementors

---

### T005 — Contract test for `ScheduleEntriesInterface` shape

**Purpose**: Lock the interface contract so regressions are caught immediately. This is a `#[CoversNothing]` contract test.

**File**: `packages/scheduler/tests/Unit/ScheduleEntriesInterfaceTest.php` (new)

**Implementation**:
```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Scheduler\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Waaseyaa\Scheduler\ScheduleEntriesInterface;
use Waaseyaa\Scheduler\ScheduledTask;
use Waaseyaa\Scheduler\ScheduleInterface;

#[CoversNothing]
final class ScheduleEntriesInterfaceTest extends TestCase
{
    #[Test]
    public function interfaceDeclaresSingleMethod(): void
    {
        $methods = (new \ReflectionClass(ScheduleEntriesInterface::class))->getMethods();
        self::assertCount(1, $methods);
        self::assertSame('register', $methods[0]->getName());
    }

    #[Test]
    public function registerMethodAcceptsScheduleInterface(): void
    {
        $method = new ReflectionMethod(ScheduleEntriesInterface::class, 'register');
        $params = $method->getParameters();
        self::assertCount(1, $params);
        self::assertSame('schedule', $params[0]->getName());
        self::assertSame(ScheduleInterface::class, $params[0]->getType()->getName());
    }

    #[Test]
    public function registerMethodReturnTypeIsArray(): void
    {
        $method = new ReflectionMethod(ScheduleEntriesInterface::class, 'register');
        self::assertSame('array', $method->getReturnType()->getName());
    }
}
```

**Validation**:
- Test passes on the new interface
- Uses `#[CoversNothing]` (PHPUnit 10.5 attribute style)
- Uses `#[Test]` attribute (not `@test` annotation)

---

### T006 — Unit test `discoversScheduleEntries` (FR-009)

**Purpose**: Verify that `PackageManifestCompiler` finds classes implementing `ScheduleEntriesInterface` and records them in the manifest.

**File**: `packages/foundation/tests/Unit/Discovery/PackageManifestCompilerTest.php` (edit — add test method, or create if absent)

**Implementation approach**:
1. Create an inline fixture class in the test (or a temp file) that implements `ScheduleEntriesInterface`:
   ```php
   // In test method or as anonymous class:
   $fixtureClass = new class implements \Waaseyaa\Scheduler\ScheduleEntriesInterface {
       public function register(\Waaseyaa\Scheduler\ScheduleInterface $schedule): array { return []; }
   };
   ```
   Note: Anonymous classes can't be discovered by FQCN. Use a named fixture class in a temp file or a pre-existing one in the scheduler package's test fixtures.

2. Alternative (simpler): Test that `AgentScheduleEntries` (which will implement the interface in WP04) is already in the list when compiling `packages/scheduler/src/`. If `AgentScheduleEntries` doesn't yet implement the interface, create a minimal fixture file at `packages/scheduler/tests/fixtures/TestScheduleEntries.php` implementing the interface for test purposes.

3. Create the compiler with test configuration pointing to `packages/scheduler/src/` (or a fixture directory).

4. Call `compile()` and assert `$manifest->scheduleEntries` contains the fixture class FQCN.

**Validation**:
- Fixture FQCN appears in `$manifest->scheduleEntries`
- Test is deterministic (no file-system side effects from other packages)
- Compilation runs in ≤ 50 ms per NFR-001 (add a timing assertion or a comment noting measurement method)

## Definition of Done

- [ ] `packages/scheduler/src/ScheduleEntriesInterface.php` exists with `@api`, correct signature, correct return type
- [ ] `PackageManifest::$scheduleEntries` field exists; `schedule_entries` key round-trips through `fromArray()`/`toArray()`
- [ ] `PackageManifestCompiler` discovers `ScheduleEntriesInterface` implementors via `class_implements()` using string constant
- [ ] `PackageManifestCompilerTest::discoversScheduleEntries` passes (FR-009)
- [ ] `ScheduleEntriesInterfaceTest` passes
- [ ] `bin/check-package-layers` passes (no upward imports from Foundation → Scheduler)
- [ ] `composer verify` green (PHPStan L5, cs-check, dead-code gate)

## Risks

| Risk | Mitigation |
|---|---|
| `PackageManifest` has no `fromArray()`/`toArray()` | Check constructor and adapt to existing serialization pattern |
| Autoloading failure in compiler scan crashes compile | Use `@class_implements()` and match existing error-handling pattern |
| String constant for interface FQCN gets out of sync on rename | Dead-code gate + PHP-CS-Fixer will not catch this — add a comment referencing the source file location |

## Reviewer Guidance

- Verify string constant FQCN matches `ScheduleEntriesInterface`'s actual namespace exactly
- Confirm no `use Waaseyaa\Scheduler\...` import appears in any Foundation file (layer discipline)
- Check `PackageManifest` for symmetric `fromArray()`/`toArray()` — asymmetric serialization causes runtime discovery gaps
- Run `bin/waaseyaa optimize:manifest` against a dev checkout and verify `schedule_entries` key appears in the compiled manifest file

## Activity Log

- 2026-05-21T00:25:52Z – claude:sonnet:implementer:implementer – shell_pid=705854 – Assigned agent via action command
- 2026-05-21T00:39:15Z – claude:sonnet:implementer:implementer – shell_pid=705854 – Interface added (L0 scheduler); PackageManifestCompiler discovers implementors via string-constant FQCN; ScheduleListHandler confirmed present; 70 tests pass, PHPStan L5 clean, cs-check clean, bin/check-package-layers passes
