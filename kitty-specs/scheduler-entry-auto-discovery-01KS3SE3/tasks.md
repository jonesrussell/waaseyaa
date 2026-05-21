# Tasks: Scheduler Entry Auto-Discovery

**Mission**: `scheduler-entry-auto-discovery-01KS3SE3`
**Branch**: `main` → `main`
**Generated**: 2026-05-20T23:57:21Z

---

## Subtask Index

| ID | Description | WP | Parallel |
|---|---|---|---|
| T001 | Define `ScheduleEntriesInterface` in `packages/scheduler/src/` | WP01 | No | [D] |
| T002 | Add `$scheduleEntries` field to `PackageManifest` (fromArray/toArray) | WP01 | [D] |
| T003 | Extend `PackageManifestCompiler::filterDiscoveryClasses()` for interface scan | WP01 | No | [D] |
| T004 | Extend `PackageManifestCompiler::compile()` to populate `schedule_entries` | WP01 | No | [D] |
| T005 | Contract test for `ScheduleEntriesInterface` shape | WP01 | [D] |
| T006 | Unit test `discoversScheduleEntries` (FR-009) | WP01 | [D] |
| T007 | Check M-B resolver landing; adopt or introduce parallel resolver | WP02 | No |
| T008 | Create `ScheduleEntryRegistry` with `boot()` method | WP02 | No |
| T009 | Create `ScheduleEntryInstantiationException` | WP02 | [P] |
| T010 | Wire `bootScheduleEntries()` into `AbstractKernel::boot()` | WP02 | No |
| T011 | Unit test `registersScheduleEntriesAtBoot` (FR-010) | WP02 | [P] |
| T012 | Unit test `failsBootOnUnresolvableScheduleEntry` (FR-011) | WP02 | [P] |
| T013 | Unit test `skipsDisabledScheduleEntries` (SC-004) | WP02 | [P] |
| T014 | Verify/add `BroadcastStorage::prune(int $retentionDays)` | WP03 | No |
| T015 | Create `BroadcastStorageScheduleEntries` implementing `ScheduleEntriesInterface` | WP03 | No |
| T016 | Unit test: prune task registers with correct cron + calls prune() | WP03 | [P] |
| T017 | Update `docs/specs/broadcasting.md` with scheduled pruning section | WP03 | [P] |
| T018 | Add `implements ScheduleEntriesInterface` to `AgentScheduleEntries` | WP04 | No |
| T019 | Verify no orphaned manual `register()` wiring in any ServiceProvider | WP04 | [P] |
| T020 | Integration test `listsBuiltInTasks` (FR-012, SC-001, SC-005) | WP04 | No |
| T021 | Integration test `pruneTaskRemovesOldRows` (FR-013, SC-002) | WP04 | [P] |
| T022 | Update `CLAUDE.md` — "Adding a schedule-entries class" checklist | WP05 | [P] |
| T023 | Update `docs/specs/operations-playbooks.md` — `schedule.disabled_entries` | WP05 | [P] |
| T024 | Extend `ScheduleListHandler` to group by entries class + show `[disabled]` | WP05 | No |
| T025 | Add `CHANGELOG.md` `[Unreleased]` entries | WP05 | [P] |

---

## Work Packages

### WP01 — `ScheduleEntriesInterface` + Manifest Discovery

**Goal**: Define the L0 interface and extend `PackageManifestCompiler` / `PackageManifest` to discover and record implementors. Establishes the foundation all other WPs depend on.
**Priority**: Critical — all WPs depend on this
**Estimated prompt size**: ~350 lines
**Dependencies**: none

#### Included subtasks

- [x] T001 Define `ScheduleEntriesInterface` in `packages/scheduler/src/` (WP01)
- [x] T002 Add `$scheduleEntries` field to `PackageManifest` (WP01)
- [x] T003 Extend `PackageManifestCompiler::filterDiscoveryClasses()` (WP01)
- [x] T004 Extend `PackageManifestCompiler::compile()` to populate `schedule_entries` (WP01)
- [x] T005 Contract test for `ScheduleEntriesInterface` shape (WP01)
- [x] T006 Unit test `discoversScheduleEntries` (WP01)

#### Implementation sketch

1. Create `packages/scheduler/src/ScheduleEntriesInterface.php` with one method, `@api`-marked
2. Add `public readonly array $scheduleEntries = []` to `PackageManifest`; update `fromArray()`/`toArray()` for `schedule_entries` key
3. Add string constant `SCHEDULE_ENTRIES_INTERFACE` to `PackageManifestCompiler`; extend `filterDiscoveryClasses()` using `class_implements()` pattern already used for `CAPABILITY_HAS_NATIVE_COMMANDS`
4. In `compile()`, populate `$scheduleEntries` list and pass to `PackageManifest` constructor
5. Add contract test confirming interface declares `register()` with correct signature
6. Add `discoversScheduleEntries` unit test with a fixture class

#### Parallel opportunities

- T002 (`PackageManifest` field) can be coded in parallel with T001 (interface definition)
- T005 and T006 (tests) can be written in parallel after T001–T004

#### Risks

- `PackageManifest::fromArray()` may not exist if manifest is fully immutable — check constructor shape before editing

---

### WP02 — Kernel Boot Wiring + Fail-Closed Assertion

**Goal**: Introduce `ScheduleEntryRegistry`, wire it into `AbstractKernel::boot()`, implement fail-closed boot on unresolvable entries, honor `schedule.disabled_entries`. Adopts M-B's resolver if available.
**Priority**: Critical — enables runtime behavior
**Estimated prompt size**: ~420 lines
**Dependencies**: WP01; conditional on M-B (`access-fail-closed-completeness-01KS3RJT`) resolver landing

#### Included subtasks

- [ ] T007 Check M-B resolver landing; adopt or introduce parallel resolver (WP02)
- [ ] T008 Create `ScheduleEntryRegistry` with `boot()` method (WP02)
- [ ] T009 Create `ScheduleEntryInstantiationException` (WP02)
- [ ] T010 Wire `bootScheduleEntries()` into `AbstractKernel::boot()` (WP02)
- [ ] T011 Unit test `registersScheduleEntriesAtBoot` (WP02)
- [ ] T012 Unit test `failsBootOnUnresolvableScheduleEntry` (WP02)
- [ ] T013 Unit test `skipsDisabledScheduleEntries` (WP02)

#### Implementation sketch

1. Check existence of `packages/foundation/src/Kernel/Bootstrap/PolicyDependencyResolverInterface.php`
2. If present: import directly; if absent: introduce `ScheduleEntryDependencyResolverInterface` with identical shape
3. Create `ScheduleEntryRegistry` — iterates manifest entries, resolves constructor, calls `register()`, throws on failure
4. Create `ScheduleEntryInstantiationException` naming FQCN + missing dep + doc link (NFR-004)
5. Add `bootScheduleEntries()` to `AbstractKernel`, call after `discoverAccessPolicies()`
6. Write three unit tests covering happy path, fail-closed, and disabled-entries skip

#### Parallel opportunities

- T009 (exception class) can be written in parallel with T008 (registry)
- T011–T013 (tests) can be written in parallel after T008–T010

#### Risks

- M-B's resolver may not have landed; implementer must branch and may need to introduce parallel interface
- `AbstractKernel` test coverage may require complex mocking of `PackageManifest` — use anonymous class fixtures

---

### WP03 — `BroadcastStorageScheduleEntries`

**Goal**: Implement the prune task for `_broadcast_log`. Document in broadcasting spec. Closes #1536.
**Priority**: High (closes production unbounded-growth bug)
**Estimated prompt size**: ~280 lines
**Dependencies**: WP02 (interface must exist; kernel wiring active)

#### Included subtasks

- [ ] T014 Verify/add `BroadcastStorage::prune(int $retentionDays)` (WP03)
- [ ] T015 Create `BroadcastStorageScheduleEntries` implementing `ScheduleEntriesInterface` (WP03)
- [ ] T016 Unit test: prune task registers with correct cron + calls prune() (WP03)
- [ ] T017 Update `docs/specs/broadcasting.md` with scheduled pruning section (WP03)

#### Implementation sketch

1. Open `packages/api/src/Controller/BroadcastStorage.php` — verify `prune(int $retentionDays = 7): void` exists; add if absent (deletes rows where `created_at < now() - interval`)
2. Create `packages/api/src/Schedule/BroadcastStorageScheduleEntries.php` — cron `0 2 * * *`, retention via `schedule.broadcast_log_retention_days` config key (default 7), `@api`-marked
3. Write unit test using mock `BroadcastStorage`; assert task identity key `prune` with correct cron
4. Add "Scheduled pruning" section to `docs/specs/broadcasting.md`

#### Parallel opportunities

- Can parallelize with WP04 after WP02 lands
- T016 (test) and T017 (doc) can be written in parallel after T014–T015

#### Risks

- `BroadcastStorage::prune()` may not exist — verify before assuming
- Layer check: `BroadcastStorageScheduleEntries` is L4, `ScheduleEntriesInterface` is L0 — valid downward dependency

---

### WP04 — Migrate `AgentScheduleEntries`

**Goal**: Wire `AgentScheduleEntries` to `ScheduleEntriesInterface`, verify end-to-end auto-discovery via integration tests. Closes #1512.
**Priority**: High (closes production silent-inertness bug)
**Estimated prompt size**: ~320 lines
**Dependencies**: WP02 (kernel wiring active)

#### Included subtasks

- [ ] T018 Add `implements ScheduleEntriesInterface` to `AgentScheduleEntries` (WP04)
- [ ] T019 Verify no orphaned manual `register()` wiring in any ServiceProvider (WP04)
- [ ] T020 Integration test `listsBuiltInTasks` (WP04)
- [ ] T021 Integration test `pruneTaskRemovesOldRows` (WP04)

#### Implementation sketch

1. Add `implements ScheduleEntriesInterface` to `AgentScheduleEntries` — signature already matches; `@api` already present
2. `grep -r "AgentScheduleEntries" packages/*/src/` — confirm no `ServiceProvider::boot()` calls `register()` manually
3. Determine next unused integration phase: `ls tests/Integration/` — plan targets Phase13, verify
4. Create `tests/Integration/Phase13/ScheduleEntryAutoDiscoveryTest.php` with `listsBuiltInTasks` and `pruneTaskRemovesOldRows`
5. `listsBuiltInTasks`: boot kernel, run `schedule:list`, assert `ai:purge-runs` (daily) and `ai:reap-stalled-runs` (*/5) and `broadcast_log_prune` (nightly) appear
6. `pruneTaskRemovesOldRows`: insert old `_broadcast_log` rows, run prune closure, assert rows gone

#### Parallel opportunities

- WP03 and WP04 can run in parallel after WP02 merges
- T019 (grep verification) is quick and can be done before T018

#### Risks

- Integration phase number may differ — must verify `ls tests/Integration/` before creating Phase13
- `pruneTaskRemovesOldRows` requires SQLite in-memory DB; verify `_broadcast_log` schema is present in test setup

---

### WP05 — Wrap-up

**Goal**: Documentation, CLI grouping update, CHANGELOG. Final `composer verify`. Closes loose ends from all prior WPs.
**Priority**: Required for merge
**Estimated prompt size**: ~260 lines
**Dependencies**: WP03, WP04 (all implementation complete)

#### Included subtasks

- [ ] T022 Update `CLAUDE.md` — "Adding a schedule-entries class" checklist (WP05)
- [ ] T023 Update `docs/specs/operations-playbooks.md` — `schedule.disabled_entries` (WP05)
- [ ] T024 Extend `ScheduleListHandler` grouping + `[disabled]` marker (WP05)
- [ ] T025 Add `CHANGELOG.md` `[Unreleased]` entries (WP05)

#### Implementation sketch

1. In `CLAUDE.md`, add "Adding a schedule-entries class" section sibling to "Adding a service provider" with 5-step checklist
2. In `docs/specs/operations-playbooks.md`, add `schedule.disabled_entries` configuration section with format and effect
3. In `packages/cli/src/Handler/ScheduleListHandler.php`: group output by owning entries class FQCN; prefix disabled entries (from manifest) with `[disabled]`
4. Add CHANGELOG `[Unreleased]` bullets: interface intro, BroadcastStorageScheduleEntries, AgentScheduleEntries migration, `Closes #1512`, `Closes #1536`
5. Run `composer verify` — confirm green

#### Parallel opportunities

- T022, T023, T025 can all be written in parallel (separate files)
- T024 (`ScheduleListHandler`) is independent of docs changes

#### Risks

- `ScheduleListHandler` format change may break existing CLI output tests — check for snapshot assertions
