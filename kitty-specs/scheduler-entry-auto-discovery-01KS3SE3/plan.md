# Implementation Plan: Scheduler Entry Auto-Discovery

**Branch**: `main` → `main` | **Date**: 2026-05-20 | **Spec**: `kitty-specs/scheduler-entry-auto-discovery-01KS3SE3/spec.md`
**Mission**: `scheduler-entry-auto-discovery-01KS3SE3`
**Closes**: #1512, #1536

---

## Branch Contract

- **Current branch at plan start**: `main`
- **Planning/base branch**: `main`
- **Merge target**: `main`
- `branch_matches_target`: true
- All WP branches land directly to `main` via squash-merge PR.

---

## Technical Context

### Existing surface (verified from source)

| Symbol | Location | Relevant notes |
|---|---|---|
| `PackageManifestCompiler` | `packages/foundation/src/Discovery/PackageManifestCompiler.php` | Scans via classmap + PSR-4 fallback. `filterDiscoveryClasses()` gates on attributes — **must be extended** to also pass `ScheduleEntriesInterface` implementors. |
| `PackageManifest` | `packages/foundation/src/Discovery/PackageManifest.php` | Immutable DTO. Needs new `$scheduleEntries` field (`list<class-string>`). `fromArray()` and `toArray()` need parallel updates. |
| `AbstractKernel::boot()` | `packages/foundation/src/Kernel/AbstractKernel.php` line 156 | Boot sequence: `discoverAndRegisterProviders()` → `bootProviders()` → `discoverAccessPolicies()`. M-D adds `bootScheduleEntries()` after `discoverAccessPolicies()`. |
| `AbstractKernel::discoverAccessPolicies()` | line 372–374 | Pattern to mirror: `new AccessPolicyRegistry($this->logger)->discover($this->manifest)`. M-D adds `new ScheduleEntryRegistry($this->logger)->boot($this->manifest, $this->schedule, $this->config)`. |
| `AgentScheduleEntries` | `packages/scheduler/src/Schedule/Ai/AgentScheduleEntries.php` | Already has the right `register()` return shape (`array{purge: ScheduledTask, reap: ScheduledTask}`). Only needs `implements ScheduleEntriesInterface` added. |
| `ScheduleListHandler` | `packages/cli/src/Handler/ScheduleListHandler.php` | **Exists** — `schedule:list` command already present. WP05 updates it to group by owning entries class and show disabled marker. |
| `BroadcastStorage` | `packages/api/src/Controller/BroadcastStorage.php` | Location confirmed. `BroadcastStorageScheduleEntries` lives alongside it in `packages/api/src/Schedule/`. |
| `PolicyDependencyResolverInterface` | `packages/foundation/src/Kernel/Bootstrap/PolicyDependencyResolverInterface.php` (M-B WP02) | M-D adopts this — see Cross-Mission section. |

### Discovery mechanism for `ScheduleEntriesInterface`

`PackageManifestCompiler::filterDiscoveryClasses()` currently gates on attribute presence. `ScheduleEntriesInterface` uses **interface implementation** (not an attribute — C-001 says interface, spec FR-002 confirms). The filter must be extended to also pass any class that implements `ScheduleEntriesInterface` by interface name string (layer discipline: string constant, not `::class` import).

Pattern already used in the compiler for `CAPABILITY_HAS_NATIVE_COMMANDS`:
```php
private const CAPABILITY_HAS_NATIVE_COMMANDS = 'Waaseyaa\\Foundation\\ServiceProvider\\Capability\\HasNativeCommandsInterface';
// ...
$implements = class_implements($providerClass);
if (is_array($implements) && isset($implements[self::CAPABILITY_HAS_NATIVE_COMMANDS])) { ... }
```

M-D reuses this exact pattern for `ScheduleEntriesInterface`.

### Resolver protocol (M-B adoption)

M-D adopts `Waaseyaa\Foundation\Kernel\Bootstrap\PolicyDependencyResolverInterface` (introduced by M-B WP02, `@api`-marked for M-D reuse). M-D's `ScheduleEntryRegistry` uses `KernelPolicyDependencyResolver` to resolve constructor parameters — identical protocol, different registry class.

**If M-B has not landed by the time WP02 of M-D is implemented:**
- M-D's implementer introduces a parallel `ScheduleEntryDependencyResolverInterface` and `KernelScheduleEntryDependencyResolver` in the same `Bootstrap/` namespace.
- M-B's plan already documents that it will adopt whichever resolver lands first.
- The two interfaces are structurally identical; consolidation happens as a follow-up (or whichever lands second renames its interface to `DependencyResolverInterface` and makes both registries use it).

**The WP02 implementer must check the state of M-B before starting.** If `PolicyDependencyResolverInterface.php` exists at `packages/foundation/src/Kernel/Bootstrap/PolicyDependencyResolverInterface.php`, adopt it directly. If not, introduce the parallel interface.

### `schedule.disabled_entries` configuration

Reads from `$this->config['schedule']['disabled_entries'] ?? []` (same config array loaded at boot). The `ScheduleEntryRegistry` receives the full config array and filters before calling `register()`.

### `schedule:list` grouping format

`ScheduleListHandler` already exists. WP05 extends it to read the manifest's `schedule_entries`, group tasks by owning class, and prefix disabled entries with `[disabled]`.

---

## Charter Check

Charter file `.kittify/charter/charter.md` — not checked (charter may not exist for this repo segment). Core governance: spec-first, no silent failures, layer discipline enforced by `bin/check-package-layers`, `composer verify` green before merge.

---

## Work Package Plan

### WP01 — `ScheduleEntriesInterface` + manifest discovery

**Goal**: Define the L0 interface, extend `PackageManifestCompiler` and `PackageManifest` to discover and record implementors.

**Files to create:**
- `packages/scheduler/src/ScheduleEntriesInterface.php` — L0 interface, one method: `public function register(ScheduleInterface $schedule): array;`, `@api`-marked.

**Files to edit:**
- `packages/foundation/src/Discovery/PackageManifestCompiler.php`
  - Add `private const SCHEDULE_ENTRIES_INTERFACE = 'Waaseyaa\\Scheduler\\ScheduleEntriesInterface';`
  - Extend `filterDiscoveryClasses()` to include classes implementing this constant (alongside the attribute-based checks).
  - Extend `compile()` to populate `$scheduleEntries` list.
- `packages/foundation/src/Discovery/PackageManifest.php`
  - Add `public readonly array $scheduleEntries = []` (`list<class-string>`) to constructor.
  - Update `fromArray()` and `toArray()` (when it exists) to handle `schedule_entries` key.

**Tests to create:**
- `packages/foundation/tests/Unit/Discovery/PackageManifestCompilerTest.php` (edit/add `discoversScheduleEntries` test — FR-009): fixture class implementing `ScheduleEntriesInterface` is scanned and appears in `$manifest->scheduleEntries`.
- `packages/scheduler/tests/Unit/ScheduleEntriesInterfaceTest.php` — contract test confirming interface shape.

**Verification**: `schedule:list` command confirmed to exist (`ScheduleListHandler`). No minimal version needed.

**Acceptance**: `bin/check-package-layers` passes. `composer verify` green. FR-001, FR-002 covered.

---

### WP02 — Kernel boot wiring + fail-closed assertion

**Goal**: Introduce `ScheduleEntryRegistry`, wire it into `AbstractKernel::boot()`, implement fail-closed boot on unresolvable entries, honor `schedule.disabled_entries`.

**Precondition check**: Implementer must check whether `packages/foundation/src/Kernel/Bootstrap/PolicyDependencyResolverInterface.php` exists (M-B landed). If yes, adopt it directly. If no, introduce `ScheduleEntryDependencyResolverInterface` with identical shape.

**Files to create:**
- `packages/foundation/src/Kernel/Bootstrap/ScheduleEntryRegistry.php`
  - Constructor: `(LoggerInterface $logger, ?PolicyDependencyResolverInterface $resolver = null)` (or parallel interface if M-B hasn't landed).
  - `boot(PackageManifest $manifest, ScheduleInterface $schedule, array $config): void`
    - For each FQCN in `$manifest->scheduleEntries`:
      - Check `$config['schedule']['disabled_entries']` — skip if present, log at info level.
      - Resolve constructor via `PolicyDependencyResolverInterface::resolveParameter()` (no `entityTypes` needed — pass `[]`).
      - On resolution failure → throw `ScheduleEntryInstantiationException` (fail-closed, names FQCN + missing dep).
      - Call `$instance->register($schedule)`.
- `packages/foundation/src/Kernel/Bootstrap/Exception/ScheduleEntryInstantiationException.php` (or reuse `PolicyInstantiationException` if M-B landed and that class is `@api`).

**Files to edit:**
- `packages/foundation/src/Kernel/AbstractKernel.php`
  - Add `protected Schedule $schedule;` (wired during `bootDatabase()` phase or a new `bootSchedule()` step).
  - Add `bootScheduleEntries()` method calling `new ScheduleEntryRegistry($this->logger)->boot($this->manifest, $this->schedule, $this->config)`.
  - Insert `$this->bootScheduleEntries()` after `$this->discoverAccessPolicies()` in `boot()`.

**Tests to create:**
- `packages/foundation/tests/Unit/Kernel/AbstractKernelTest.php` (add):
  - `registersScheduleEntriesAtBoot` (FR-010): kernel with a mock manifest containing one entries class calls `register()` on boot.
  - `failsBootOnUnresolvableScheduleEntry` (FR-011): kernel boot throws when entries class has unresolvable dep.
  - `skipsDisabledScheduleEntries` (SC-004): entry in `schedule.disabled_entries` is not instantiated.

**Acceptance**: FR-003, FR-004, FR-007 covered. `composer verify` green.

---

### WP03 — `BroadcastStorageScheduleEntries`

**Goal**: Implement the prune task for `_broadcast_log`. Closes #1536.

**Cron decision**: Nightly at `0 2 * * *` (02:00 UTC) — off-peak, distinct from `AgentScheduleEntries`'s 03:00 purge slot. Retention window: 7 days (configurable via `schedule.broadcast_log_retention_days`, default `7`).

**Files to create:**
- `packages/api/src/Schedule/BroadcastStorageScheduleEntries.php`
  - `implements ScheduleEntriesInterface`
  - Constructor: `(BroadcastStorage $broadcastStorage, array $config = [])` — `BroadcastStorage` is in the same package (L4), no layer violation.
  - `register()`: adds one `ScheduledTask` with cron `0 2 * * *`, calls `$this->broadcastStorage->prune($retentionDays)`.
  - Returns `['prune' => $pruneTask]`.
  - `@api`-marked.

**Files to edit:**
- `packages/api/src/Controller/BroadcastStorage.php` — verify `prune(int $retentionDays = 7): void` exists; if not, add it (deletes rows where `created_at < now() - retentionDays`).
- `docs/specs/broadcasting.md` — add section: "Scheduled pruning", document cron expression, retention default, and opt-out via `schedule.disabled_entries`.

**Tests to create:**
- `packages/api/tests/Unit/Schedule/BroadcastStorageScheduleEntriesTest.php` — unit test that `register()` adds one task with correct cron and that `prune()` is called.

**Acceptance**: FR-006, NFR-003 covered. `bin/check-package-layers` passes (L4 class implements L0 interface — downward, valid). `composer verify` green.

---

### WP04 — Migrate `AgentScheduleEntries`

**Goal**: Wire `AgentScheduleEntries` to the new interface. Verify end-to-end discoverability. Closes #1512.

**Files to edit:**
- `packages/scheduler/src/Schedule/Ai/AgentScheduleEntries.php`
  - Add `implements ScheduleEntriesInterface` (signature already matches FR-001).
  - `@api` annotation already present — no change needed.
  - Verify no manual `register()` call exists in any `ServiceProvider::boot()` (there shouldn't be — that's why #1512 is open). Search: `grep -r "AgentScheduleEntries" packages/*/src/`.

**Tests to create/extend:**
- `tests/Integration/Phase13/ScheduleEntryAutoDiscoveryTest.php`
  - `listsBuiltInTasks` (FR-012, SC-001, SC-005): boots kernel with built-in entries, invokes `schedule:list`, asserts `ai:purge-runs` and `ai:reap-stalled-runs` appear with correct cron expressions.
  - `pruneTaskRemovesOldRows` (FR-013, SC-002): inserts old `_broadcast_log` rows, runs `BroadcastStorageScheduleEntries::register()` + prune task, asserts rows deleted.
  - Phase number `13` — verify next unused integration phase before creating (check `tests/Integration/` for existing phase dirs).

**Acceptance**: FR-005, FR-012, FR-013, SC-001, SC-002, SC-005 covered. `composer verify` green.

---

### WP05 — Wrap-up

**Goal**: Documentation, CLI update, CHANGELOG, final `composer verify`.

**Files to edit:**
- `CLAUDE.md` — add "Adding a schedule-entries class" checklist sibling to "Adding a service provider":
  1. Create class implementing `ScheduleEntriesInterface` in `packages/<name>/src/Schedule/`
  2. Mark `@api`
  3. Ensure constructor deps are container-resolvable
  4. Run `bin/waaseyaa optimize:manifest` (or restart dev server)
  5. Verify with `bin/waaseyaa schedule:list`
- `docs/specs/operations-playbooks.md` (or equivalent) — add `schedule.disabled_entries` section: how to opt out of a built-in entries class, format (`list<class-string>`), effect (entries silently skipped, shown as `[disabled]` in `schedule:list`).
- `packages/cli/src/Handler/ScheduleListHandler.php` — extend to group tasks by owning `*ScheduleEntries` class FQCN (FR-008), show `[disabled]` prefix for skipped entries.
- `CHANGELOG.md` — add `[Unreleased]` entries for: ScheduleEntriesInterface, BroadcastStorageScheduleEntries, AgentScheduleEntries migration, closes #1512, closes #1536.

**Commit footer** (on merge commit): `Closes #1512`, `Closes #1536`.

**Acceptance**: FR-008, C-003, SC-006, SC-007 covered. `composer verify` green. Full CI green.

---

## Data Model

### `PackageManifest` — new field

```php
/** @var list<class-string> FQCNs implementing ScheduleEntriesInterface, discovered at compile time. */
public readonly array $scheduleEntries = [],
```

Manifest cache key: `schedule_entries` (snake_case, consistent with `field_types`, `agent_tools`, etc.).

### `ScheduleEntriesInterface`

```php
namespace Waaseyaa\Scheduler;

/** @api */
interface ScheduleEntriesInterface
{
    /**
     * Register recurring tasks on the supplied schedule.
     *
     * @return array<string, ScheduledTask> Keyed by task identity string.
     */
    public function register(ScheduleInterface $schedule): array;
}
```

### `ScheduleEntryRegistry`

```php
namespace Waaseyaa\Foundation\Kernel\Bootstrap;

final class ScheduleEntryRegistry
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function boot(PackageManifest $manifest, ScheduleInterface $schedule, array $config): void;
}
```

Uses `PolicyDependencyResolverInterface` (M-B) or parallel `ScheduleEntryDependencyResolverInterface` (if M-B not landed). Throws `ScheduleEntryInstantiationException` on resolution failure.

---

## Integration Phase

Integration tests live in `tests/Integration/Phase13/` — implementer verifies this is the next unused phase by listing `tests/Integration/` before creating the directory.

---

## Cross-Mission Interaction (M-B)

M-D's WP02 **adopts** `Waaseyaa\Foundation\Kernel\Bootstrap\PolicyDependencyResolverInterface` (M-B's resolver, `@api`-marked for this reuse). The implementer checks for the file's existence before starting WP02 and branches on availability. The FQCN is canonical regardless of which mission lands first.

---

## NFR Targets

| NFR | Target | Verification |
|---|---|---|
| NFR-001 | ≤50 ms added to manifest compile | Benchmark added in WP01 test or measured against existing compile benchmark |
| NFR-002 | ≤2 ms per entry at boot | Kernel boot benchmark (2 built-in entries → ≤4 ms total) |
| NFR-003 | Prune cron = `0 2 * * *`, retention = 7 days (configurable) | Documented in `docs/specs/broadcasting.md` and tested in WP03 |
| NFR-004 | Fail-closed exception names FQCN + dep type + doc link | Exception message format in `ScheduleEntryInstantiationException` |

---

## Gate Checklist

Before each WP merge:
- [ ] `bin/check-package-layers` passes
- [ ] `bin/check-composer-policy` passes
- [ ] `composer verify` green (PHPStan + cs-check + dead-code gate)
- [ ] No `--no-verify` hooks bypassed (C-008)
- [ ] `self.version` / `^<current-tag>` constraints correct in any new `composer.json` (not applicable — no new packages)

---

## Next Step

Run `/spec-kitty.tasks` to generate work package files from this plan.
