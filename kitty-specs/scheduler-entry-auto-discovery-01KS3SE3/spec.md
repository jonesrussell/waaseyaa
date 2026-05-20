# Scheduler Entry Auto-Discovery

**Mission:** `scheduler-entry-auto-discovery-01KS3SE3`
**Status:** Spec
**Target branch:** `main`
**Cross-references:** M-B (`access-fail-closed-completeness-01KS3RJT`) — container-resolved resolver pattern is reused here.
**Closes:** #1512, #1536

## Why this mission exists

`packages/scheduler/src/Schedule/Ai/AgentScheduleEntries.php` declares two production recurring jobs — `ai:purge-runs` daily, `ai:reap-stalled-runs` every five minutes — and **nothing calls `register()` on it**. The class carries `@api` (which keeps the dead-code detector from complaining about it) and a usage example in its docblock, but no service provider invokes `$entries->register($schedule)` at boot. Under cron, neither task fires. The agent runtime's retention sweep and crash-recovery reaper, both spec'd as `Mandatory` by the predecessor mission, are silently inert in production.

The same gap-shape produced #1536: `BroadcastStorage::push()` writes to `_broadcast_log` on every entity save/delete, and `BroadcastStorage::prune()` exists but is never called on a schedule. Minoo's local DB had **243 rows** dating back to 2026-03 testing — the table is an unbounded leak per consumer install. Even after #1535 fixed history replay, the table still grows on every write and every SSE poll walks a growing index.

**The pattern.** Both issues are symptoms of one architectural gap: there is no auto-discovery path for schedule entries. Every other extension point in Waaseyaa — policies (M-B), middleware, service providers, ingestion mappers, route providers, knowledge extensions — is discovered by the `PackageManifestCompiler` via attribute or interface scanning. Schedule entries are not. So when an L0 author writes a `*ScheduleEntries` class, there is nowhere for it to plug in; the responsibility falls on each package's `ServiceProvider::boot()` to remember the wiring, and the framework's first schedule entry (`AgentScheduleEntries`) **forgot it**. The next one will too.

**The fix.** Introduce `ScheduleEntriesInterface` (L0 scheduler), have `PackageManifestCompiler` scan for implementors and write them into the manifest, and have the kernel enumerate the manifest at boot to call `register()` on each — using the same container-resolved resolver protocol M-B introduces for access policies. Migrate `AgentScheduleEntries` to implement the new interface. Add `BroadcastStorageScheduleEntries` (or similar) for the prune job. Add a fail-closed boot assertion: any `*ScheduleEntries` class discovered in the manifest must successfully register at boot or kernel boot fails.

The mission's contract: after merge, a developer writing a new `*ScheduleEntries` class needs only to (1) implement `ScheduleEntriesInterface` and (2) ensure their constructor dependencies are container-resolvable. No service provider wiring required; no silent inertness possible.

## User scenarios

### Primary flow: a developer adds a new recurring job

1. Developer adds `packages/foo/src/Schedule/FooScheduleEntries.php` implementing `ScheduleEntriesInterface` with constructor dependencies typed to existing framework services (e.g. `LoggerInterface`, a `FooService`).
2. They run `bin/waaseyaa optimize:manifest` (or just restart the dev server).
3. The `PackageManifestCompiler` scans, finds the new class, and records it in the manifest.
4. On next kernel boot, the kernel resolves the class's constructor arguments via the container, instantiates it, and calls `register($schedule)`.
5. `bin/waaseyaa schedule:list` shows the new task(s) in the registered set, including the cron expression and command name.
6. Cron picks the task up on its next tick.

### Primary flow: a fresh install gets retention + crash-recovery + log pruning for free

1. Consumer installs Waaseyaa for the first time. No app code yet.
2. Consumer runs `bin/waaseyaa schedule:run` from cron.
3. The framework's built-in schedule entries fire:
   - `AgentScheduleEntries`: `ai:purge-runs` daily, `ai:reap-stalled-runs` every 5 minutes.
   - `BroadcastStorageScheduleEntries`: `_broadcast_log` prune nightly (or on a TBD cron — planner picks during WP02).
4. Consumer never wrote a single line of scheduler wiring.

### Recovery flow: an unresolvable dependency in a schedule-entries class fails boot

1. Developer writes `FooScheduleEntries` whose constructor requires `BarServiceInterface` but no provider binds it.
2. `composer dump-autoload --optimize` succeeds (the class is structurally valid).
3. Kernel boot attempts to instantiate `FooScheduleEntries`, the resolver cannot find a binding for `BarServiceInterface`, and **kernel boot fails** with an exception naming the schedule-entries class and the missing dependency.
4. The error message tells the developer exactly where to bind the missing service — same actionable pattern as M-B's policy-resolution failure.

### Edge cases

- **Schedule entries with no constructor dependencies.** A `*ScheduleEntries` class with `__construct()` (no args) is auto-instantiated trivially.
- **Schedule entries declaring multiple tasks.** A single `*ScheduleEntries` class can call `$schedule->add()` multiple times — `AgentScheduleEntries` already does (two tasks). The interface's contract does not constrain task count.
- **Schedule entries returning task data.** The current `AgentScheduleEntries::register()` returns an array of added tasks for introspection by `bin/waaseyaa schedule:list`. The new interface formalizes this return type — `array<string, ScheduledTask>` keyed by task identity.
- **CLI-only commands invoked from cron.** `AgentScheduleEntries` uses a closure invoker pattern so L0 scheduler can run L6 CLI commands without an upward layer dependency. The interface preserves this pattern: schedule-entries classes carrying CLI commands accept a `\Closure(string): int` (or framework-typed invoker) in their constructor, and the container resolves it just like any other dependency.
- **Disabling a built-in.** A consumer wants to disable `BroadcastStorageScheduleEntries` because their workload calls `prune()` differently. The mission offers an opt-out via configuration (`schedule.disabled_entries: [...]`) checked by the kernel before invoking `register()`. If a consumer disables a built-in entry, no error; the entry is simply skipped.

## Requirements

### Functional

| ID | Status | Requirement |
|---|---|---|
| FR-001 | Mandatory | `Waaseyaa\Scheduler\ScheduleEntriesInterface` exists with one declared method: `public function register(ScheduleInterface $schedule): array;` — return type is `array<string, ScheduledTask>` keyed by task identity (matches the existing `AgentScheduleEntries::register()` shape). |
| FR-002 | Mandatory | `PackageManifestCompiler` discovers every class implementing `ScheduleEntriesInterface` across `packages/*/src/` and records their FQCNs in the manifest under a `schedule_entries` (or equivalently named) field. Discovery uses interface implementation, not attributes. |
| FR-003 | Mandatory | `AbstractKernel::boot()` (or a sibling bootstrap step) enumerates the manifest's `schedule_entries`, resolves each class's constructor via the container-resolved resolver protocol introduced by M-B (or, if M-D lands first, M-D introduces its own constructor-resolving capability and M-B's mission adopts it), instantiates the class, and calls `register($schedule)`. |
| FR-004 | Mandatory | If a `ScheduleEntriesInterface` implementor cannot be resolved at boot (unresolvable dependency, constructor throws), **kernel boot fails** with an exception naming the schedule-entries class and the failing dependency. No silent skip. The same fail-closed pattern as M-B's `AccessPolicyRegistry`. |
| FR-005 | Mandatory | `AgentScheduleEntries` implements `ScheduleEntriesInterface`. Its `register()` signature is unchanged (already matches the contract). Its `@api` annotation remains. |
| FR-006 | Mandatory | A new `Waaseyaa\Api\Schedule\BroadcastStorageScheduleEntries` (or equivalent path under whichever package owns `BroadcastStorage`) implements `ScheduleEntriesInterface`. It registers one task that calls `BroadcastStorage::prune()` on a cron schedule. The cron expression and retention window are documented in the class doc and `docs/specs/broadcasting.md`. |
| FR-007 | Mandatory | Consumer-facing opt-out: a configuration key `schedule.disabled_entries: list<class-string>` is honored. The kernel skips `register()` on any entry whose FQCN appears in the list. Empty list (default) = all entries fire. |
| FR-008 | Mandatory | `bin/waaseyaa schedule:list` shows all discovered + registered tasks, grouped by their owning `*ScheduleEntries` class. Disabled entries (per FR-007) show as `disabled` and do not appear in the active set. |
| FR-009 | Mandatory | Unit test: `PackageManifestCompilerTest::discoversScheduleEntries` covers manifest discovery — a fixture class implementing `ScheduleEntriesInterface` is found and recorded. |
| FR-010 | Mandatory | Unit test: `AbstractKernelTest::registersScheduleEntriesAtBoot` covers the kernel's boot-time `register()` invocation for each manifest entry. |
| FR-011 | Mandatory | Unit test: `AbstractKernelTest::failsBootOnUnresolvableScheduleEntry` covers FR-004's fail-closed assertion. |
| FR-012 | Mandatory | Integration test under `tests/Integration/Phase??/ScheduleEntryAutoDiscoveryTest.php` boots the kernel with the framework's built-in entries (`AgentScheduleEntries`, `BroadcastStorageScheduleEntries`), invokes `schedule:list`, and asserts all expected tasks appear with the right cron expressions. |
| FR-013 | Mandatory | Integration test asserts the `BroadcastStorageScheduleEntries`'s prune task actually deletes old `_broadcast_log` rows when run (covers #1536's effective behavior, not just registration). |

### Non-functional

| ID | Status | Threshold |
|---|---|---|
| NFR-001 | Mandatory | Manifest discovery of schedule entries adds ≤ 50 ms to the existing manifest-compile time on a clean checkout (≤62 packages today). Measured against an existing `PackageManifestCompiler` benchmark or one added in WP01. |
| NFR-002 | Mandatory | Kernel boot's per-entry `register()` invocation adds ≤ 2 ms median per entry, measured against an existing kernel-boot benchmark. With 2 built-in entries today, total overhead ≤ 4 ms. |
| NFR-003 | Mandatory | `BroadcastStorageScheduleEntries`'s prune task default cron is documented and aligned with the retention requirement (planner picks; my recommendation: nightly with a 7-day window). The retention window is overridable via configuration. |
| NFR-004 | Mandatory | The fail-closed boot exception (FR-004) names the schedule-entries class FQCN, the failing dependency type, and links to the docs section (or memory) that explains the resolver protocol. |

### Constraints

| ID | Status | Constraint |
|---|---|---|
| C-001 | Mandatory | `ScheduleEntriesInterface` lives in `packages/scheduler/` (L0). No upward layer imports. |
| C-002 | Mandatory | `BroadcastStorageScheduleEntries` lives wherever `BroadcastStorage` lives (currently `packages/api/`, L4). It implements the L0 interface (downward), and may import L4 services (its own package). Closure invoker pattern is preserved if any cross-layer command dispatch is needed. |
| C-003 | Mandatory | The merge commit closes #1512 and #1536 via `Closes #N` footer. |
| C-004 | Mandatory | The container-resolved resolver protocol used at FR-003 is **shared with M-B**. If M-B lands first, this mission imports and uses M-B's resolver interface. If this mission lands first, the resolver protocol introduced here is the canonical one, and M-B's WP02 adopts it. The two missions explicitly coordinate (this is documented in both specs' "Cross-mission interaction" notes). |
| C-005 | Mandatory | No changes to `ScheduleInterface`, `ScheduledTask`, or the existing scheduler runner. The mission adds a discovery + boot-wiring layer; it does not refactor the scheduler core. |
| C-006 | Mandatory | The mission preserves the L0→L6 layer architecture (`bin/check-package-layers` passes). |
| C-007 | Mandatory | `composer verify` is green on the merge commit. |
| C-008 | Mandatory | No CI hooks bypassed during this mission's PRs. |

## Success criteria

| ID | Metric | How verified |
|---|---|---|
| SC-001 | Framework built-in schedule entries fire on a fresh install with no consumer wiring. | Integration test `ScheduleEntryAutoDiscoveryTest::listsBuiltInTasks` passes (FR-012). |
| SC-002 | `_broadcast_log` is pruned automatically. | Integration test `ScheduleEntryAutoDiscoveryTest::pruneTaskRemovesOldRows` passes (FR-013). |
| SC-003 | A `*ScheduleEntries` class with an unresolvable dependency fails kernel boot. | `AbstractKernelTest::failsBootOnUnresolvableScheduleEntry` passes (FR-011). |
| SC-004 | A consumer can disable a built-in schedule-entries class via configuration. | Unit test on the kernel's disabled-entries skip path passes. |
| SC-005 | `AgentScheduleEntries`'s tasks (`ai:purge-runs`, `ai:reap-stalled-runs`) fire on a fresh install. Closes #1512. | Verified by `SC-001`'s integration test enumerating the task names. |
| SC-006 | `composer verify` is green on the merge commit. | CI status check `verify` passes on the merging PR. |
| SC-007 | Issues #1512 and #1536 close on merge. | GitHub auto-closes via `Closes #N` footer. |

## Key entities

| Entity | Role | Net change in this mission |
|---|---|---|
| `Waaseyaa\Scheduler\ScheduleEntriesInterface` (new) | L0 contract for discoverable schedule entries. | +1 file. |
| `PackageManifestCompiler` | L0 manifest compiler. | Edit: add `ScheduleEntriesInterface` discovery. |
| `AbstractKernel` | L0 kernel bootstrap. | Edit: enumerate manifest's `schedule_entries`, resolve + register at boot. |
| `AgentScheduleEntries` | Existing L0 scheduler entry. | Edit: add `implements ScheduleEntriesInterface`. |
| `BroadcastStorageScheduleEntries` (new) | L4 schedule entry for `_broadcast_log` prune. | +1 file. |
| Configuration key `schedule.disabled_entries` | Consumer opt-out for built-ins. | Edit `docs/specs/operations-playbooks.md` or equivalent. |
| `bin/waaseyaa schedule:list` | Existing CLI command. | Edit: show entries grouped, include disabled marker. |
| Unit tests (3 files) + integration tests (1+ files) | New regression coverage per FR-009..FR-013. | +4 files (or edits). |
| `docs/specs/broadcasting.md` | Document the prune task. | Edit. |
| `docs/specs/operations-playbooks.md` (or equivalent) | Document the `disabled_entries` opt-out. | Edit. |
| `CLAUDE.md` | "Adding a service provider" section gains a sibling: "Adding a schedule-entries class." | Edit. |
| `CHANGELOG.md` | `[Unreleased]` entry. | Edit. |

## Assumptions

- The container-resolved resolver protocol is shared with M-B. Whichever mission lands first introduces it; the second mission adopts. Both specs explicitly call out the dependency.
- `PackageManifestCompiler` already scans `packages/*/src/` for interface implementations (it does, for other contracts). Adding another interface to scan is a localized change, not a new compiler pattern.
- `BroadcastStorage` lives in `packages/api/`. If a future mission moves it (e.g. to a dedicated `packages/broadcasting/`), the schedule-entries class moves with it. The mission doesn't depend on the current location.
- The default prune retention window (recommended: 7 days, configurable) is a reasonable balance between debuggability and table size. If a consumer needs different retention, configuration covers it.
- The `bin/waaseyaa schedule:list` command exists. If it doesn't, WP04 adds it as a minimum (the spec mentions it as if it does — planner verifies in WP01).

## Out of scope

- Refactoring the scheduler runner.
- Adding new cron expression syntax or extending `ScheduledTask`.
- Web UI for the schedule list (admin SPA work is out — covered by #1471 / M4B as a separate mission).
- Distributed-lock improvements for `preventOverlap: true` semantics.
- Migrating other recurring jobs the framework may grow (those adopt the new interface as they are added).
- Backporting auto-discovery to consumer apps' existing manual `ServiceProvider::boot()` schedule wiring — consumers migrate at their own pace; the new interface is additive.

## WP outline (for /spec-kitty.plan)

The planner is free to revise. Indicative shape:

- **WP01 — `ScheduleEntriesInterface` + manifest discovery.** Define the interface. Extend `PackageManifestCompiler` to scan for implementors. Unit tests (FR-009). Verify the `bin/waaseyaa schedule:list` command exists (or add a minimal version).
- **WP02 — Kernel boot wiring + fail-closed assertion.** Enumerate the manifest at boot, resolve each entry via the container-resolved resolver protocol (coordinate with M-B), invoke `register()`. Fail-closed boot on unresolvable dependencies (FR-004). Honor `schedule.disabled_entries` configuration (FR-007). Unit tests (FR-010, FR-011). Closes #1512 for `AgentScheduleEntries`.
- **WP03 — `BroadcastStorageScheduleEntries`.** Implement the prune task. Default cron and retention window. Document in `docs/specs/broadcasting.md`. Closes #1536.
- **WP04 — Migrate `AgentScheduleEntries`.** Add `implements ScheduleEntriesInterface` (signature unchanged). Verify discoverability via integration test (FR-012). Remove any manual wiring left over from the predecessor mission (none exists in production today — that's why #1512 is open — but verify).
- **WP05 — Wrap-up.** Update CLAUDE.md with a "Adding a schedule-entries class" checklist. Update `docs/specs/operations-playbooks.md` (or equivalent) with `disabled_entries` documentation. `CHANGELOG.md` entry. Full `composer verify` green.

## References

- `AgentScheduleEntries.php` source: line 68 `register(ScheduleInterface $schedule): array`, line 12 docblock explaining the closure-invoker pattern + layer-compliance constraints.
- `ScheduleInterface.php`: minimal contract (`tasks()`, `add()`).
- `PackageManifestCompiler` — the existing scanner that already discovers policies, middleware, providers, ingestion mappers, etc.
- CLAUDE.md: "Service providers" checklist (for the new schedule-entries sibling).
- Memory: `feedback_modern_php_rules.md` — typed interfaces only, contract tests for every extension point (FR-009..FR-011 motivation).
- Memory: `feedback_regression_tests.md` — always write regression tests when fixing bugs (FR-009..FR-013).
- M-B (`access-fail-closed-completeness-01KS3RJT`): shares the container-resolved resolver protocol (FR-003, C-004).
- #1536 evidence: Minoo's local DB had 243 rows from 2026-03 testing (cited in issue body).
