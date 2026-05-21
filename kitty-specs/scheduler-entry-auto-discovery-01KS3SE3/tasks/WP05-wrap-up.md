---
work_package_id: WP05
title: Wrap-up
dependencies:
- WP03
- WP04
requirement_refs:
- C-003
- FR-008
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T022
- T023
- T024
- T025
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "829845"
history:
- date: '2026-05-20T23:57:21Z'
  event: created
authoritative_surface: packages/cli/src/Handler/
execution_mode: code_change
owned_files:
- CLAUDE.md
- docs/specs/operations-playbooks.md
- packages/cli/src/Handler/ScheduleListHandler.php
- CHANGELOG.md
tags: []
---

# WP05 — Wrap-up

## Objective

Complete the mission: update developer documentation (CLAUDE.md), add operator guidance for the `disabled_entries` opt-out, extend `ScheduleListHandler` to group tasks by owning class and show disabled markers, and add the CHANGELOG entry. Confirm `composer verify` green. Merge closes #1512 and #1536 via `Closes #N` footer.

**Requirement coverage**: FR-008, C-003, SC-006, SC-007

## Context

### What WP05 owns

WP05 is a documentation + CLI polish WP. All framework behavior is already live after WP01–WP04. This WP makes the system observable and maintainable:

1. **CLAUDE.md**: Developers adding new schedule entries need a checklist. Right now, CLAUDE.md has "Adding a service provider" but nothing for schedule entries. This WP adds the sibling checklist.

2. **`docs/specs/operations-playbooks.md`**: Operators need to know how to opt out of a built-in schedule entry. The `schedule.disabled_entries` configuration key must be documented with format, effect, and examples.

3. **`ScheduleListHandler`**: `bin/waaseyaa schedule:list` already exists. WP05 extends it to group output by owning `*ScheduleEntries` class and prefix disabled entries with `[disabled]`. This makes it clear which auto-discovered class owns each task.

4. **CHANGELOG.md**: Standard `[Unreleased]` entries per `feedback_changelog_release_workflow.md` memory note.

### `ScheduleListHandler` current behavior

The handler exists at `packages/cli/src/Handler/ScheduleListHandler.php`. Before editing, open it to understand current output format. It likely lists tasks in a flat format. After WP05:
- Tasks are grouped by owning `*ScheduleEntries` FQCN
- The owning class FQCN is shown as a section header
- Disabled entries (from manifest's `schedule_entries` vs `schedule.disabled_entries` config diff) show as `[disabled]`
- Active tasks show their cron expression and task identity

**Important**: Check if there are existing tests for `ScheduleListHandler` that assert output format. If so, update them to match the new format. If not, add a test.

### Commit footer for merge commit

The PR description (or squash-merge commit body) must include:
```
Closes #1512
Closes #1536
```

This satisfies C-003 and SC-007. Verify in the PR template when filing.

## Branch Strategy

- **Planning/base branch**: `main`
- **Merge target**: `main`
- **Depends on**: WP03 and WP04 (all behavior complete)
- **Last WP to merge** — this is the final cleanup step
- **Execution**: `spec-kitty agent action implement WP05 --agent <name>`

## Subtask Guidance

### T022 — Update `CLAUDE.md` — "Adding a schedule-entries class" checklist

**Purpose**: Give developers a self-contained checklist for adding a new schedule-entries class. Sibling to the existing "Adding a service provider" section.

**File**: `CLAUDE.md` (edit — read the file first to find the right insertion point)

**Steps**:
1. Open `CLAUDE.md` and locate the "Adding a service provider" checklist under "Operation Checklists".
2. Add a new checklist immediately after it:

```markdown
**Adding a schedule-entries class:**
1. Create class implementing `ScheduleEntriesInterface` in `packages/<name>/src/Schedule/`
2. Mark with `@api` in PHPDoc (required — dead-code detector gates on this)
3. Declare a `register(ScheduleInterface $schedule): array` method returning `array<string, ScheduledTask>`
4. Ensure constructor dependencies are container-resolvable (type-hint to interfaces bound by service providers)
5. Run `bin/waaseyaa optimize:manifest` (or restart dev server) to trigger discovery
6. Verify with `bin/waaseyaa schedule:list` — your tasks should appear grouped under the class FQCN
7. To disable a built-in entry: add its FQCN to `schedule.disabled_entries` in configuration
```

**Validation**:
- Checklist is in the "Operation Checklists" section
- Steps 1–7 are present
- `ScheduleEntriesInterface` is spelled correctly
- `@api` requirement is mentioned (dead-code gate)

---

### T023 — Update `docs/specs/operations-playbooks.md` — `schedule.disabled_entries`

**Purpose**: Operators need to know how to opt out of built-in schedule entries. Document the configuration key with format, effect, and two examples.

**File**: `docs/specs/operations-playbooks.md` (edit — read to find insertion point)

**Steps**:
1. Open the file. Find an appropriate section (likely "Configuration" or "Scheduler" or create a new "Schedule Entries" section).
2. Add:

```markdown
## Schedule Entry Auto-Discovery

Waaseyaa automatically discovers and registers all classes implementing
`ScheduleEntriesInterface` at kernel boot. No manual service-provider wiring is needed.

### Built-in schedule entries

| Class | Tasks | Cron |
|---|---|---|
| `Waaseyaa\Scheduler\Schedule\Ai\AgentScheduleEntries` | `ai:purge-runs` | Daily (`0 0 * * *`) |
| `Waaseyaa\Scheduler\Schedule\Ai\AgentScheduleEntries` | `ai:reap-stalled-runs` | Every 5 min (`*/5 * * * *`) |
| `Waaseyaa\Api\Schedule\BroadcastStorageScheduleEntries` | `broadcast_log_prune` | Nightly (`0 2 * * *`) |

Verify exact cron expressions with `bin/waaseyaa schedule:list`.

### Disabling a built-in schedule entry

Set `schedule.disabled_entries` to a list of class-string FQCNs:

```yaml
schedule:
  disabled_entries:
    - Waaseyaa\Api\Schedule\BroadcastStorageScheduleEntries
```

**Effect**:
- The entry is not instantiated at boot
- `bin/waaseyaa schedule:list` shows the entry as `[disabled]`
- The underlying task (e.g. `broadcast_log_prune`) never runs

**When to disable**:
- You manage pruning externally (database maintenance job, custom cron script)
- You want to replace a built-in entry with your own implementation
- You are testing and want to suppress background tasks

**Warning**: Disabling `AgentScheduleEntries` stops the AI runtime's retention sweep
(`ai:purge-runs`) and crash-recovery reaper (`ai:reap-stalled-runs`). The agent run
table will grow without bound and stalled runs will never be reaped. Disable only if
you handle these operations externally.
```

**Validation**:
- Section present with built-in entries table
- `schedule.disabled_entries` YAML format shown
- Effects and warnings documented
- Cron expressions match what the classes actually register

---

### T024 — Extend `ScheduleListHandler` grouping + `[disabled]` marker (FR-008)

**Purpose**: Make `bin/waaseyaa schedule:list` observable — show which auto-discovered class owns each task, and mark disabled entries clearly.

**File**: `packages/cli/src/Handler/ScheduleListHandler.php` (edit)

**Steps**:
1. **Read the current handler** to understand existing output format and data sources.
2. Determine how the handler gets schedule data today (likely from `Schedule::tasks()` or injected at construction).
3. Add access to the manifest's `schedule_entries` list and the `schedule.disabled_entries` config list.
4. Build output grouped by owning class:

**New output format**:
```
Waaseyaa\Scheduler\Schedule\Ai\AgentScheduleEntries
  ai:purge-runs          0 0 * * *
  ai:reap-stalled-runs   */5 * * * *

Waaseyaa\Api\Schedule\BroadcastStorageScheduleEntries
  broadcast_log_prune    0 2 * * *

[disabled] Waaseyaa\Foo\Schedule\FooScheduleEntries
  (not registered — disabled by schedule.disabled_entries)
```

**Implementation sketch**:
```php
// In ScheduleListHandler::handle() or similar:
$scheduleEntries = $this->manifest->scheduleEntries; // all discovered FQCNs
$disabledEntries = $this->config['schedule']['disabled_entries'] ?? [];
$registeredTasks = $this->schedule->tasks(); // all active tasks, keyed by identity

foreach ($scheduleEntries as $fqcn) {
    $isDisabled = in_array($fqcn, $disabledEntries, true);
    $prefix = $isDisabled ? '[disabled] ' : '';
    $this->output->writeln($prefix . $fqcn);

    if (!$isDisabled) {
        // Find tasks owned by this entries class
        // (requires tasks to carry owning-class metadata, OR
        //  requires matching task identity keys to entries classes)
        foreach ($registeredTasks as $identity => $task) {
            // Show tasks owned by this class
            // ... how to associate tasks with owning class? See note below.
        }
    } else {
        $this->output->writeln('  (not registered — disabled by schedule.disabled_entries)');
    }
}
```

**Association challenge**: Tasks need to be associated with their owning `*ScheduleEntries` class for grouping. Options:
1. `ScheduleEntryRegistry::boot()` returns (or stores) a map of `FQCN → array<string, ScheduledTask>` that the handler can read.
2. `ScheduledTask` carries an `ownerClass` property set during `register()`.
3. The handler calls `register()` fresh on each class (read-only, not committing to the live schedule) to discover which task identities each class produces.

Pick the simplest approach that doesn't require adding a new property to `ScheduledTask` (C-005: no changes to scheduler core). Option 3 (fresh register call) is safest but requires a mock/no-op schedule. Look at how `schedule:list` currently works and extend incrementally.

**Validation**:
- Output groups tasks by owning class FQCN
- Disabled entries show `[disabled]` prefix
- Active entries show task identity + cron expression
- Existing CLI output tests (if any) updated for new format

---

### T025 — Add `CHANGELOG.md` `[Unreleased]` entries

**Purpose**: Document the changes per project convention (`feedback_changelog_release_workflow.md`).

**File**: `CHANGELOG.md` (edit)

**Steps**:
1. Open `CHANGELOG.md` and find the `[Unreleased]` section.
2. Add bullets under the appropriate subsection (Added / Fixed / Changed):

```markdown
### Added
- `Waaseyaa\Scheduler\ScheduleEntriesInterface`: auto-discoverable contract for recurring task registrations. Implementors are discovered by `PackageManifestCompiler` and registered at kernel boot with fail-closed dependency resolution.
- `Waaseyaa\Api\Schedule\BroadcastStorageScheduleEntries`: nightly `_broadcast_log` prune task (cron `0 2 * * *`, 7-day default retention, configurable). Closes #1536.
- `schedule.disabled_entries` configuration key: opt-out list for built-in schedule-entries classes.
- `bin/waaseyaa schedule:list` now groups tasks by owning `*ScheduleEntries` class and shows `[disabled]` for opt-out entries.

### Fixed
- `AgentScheduleEntries` (`ai:purge-runs`, `ai:reap-stalled-runs`) now auto-discovered and registered at boot; previously, no code called `register()` and both tasks were silently inert in production. Closes #1512.
```

**Important**: Do NOT add a version heading — only `[Unreleased]` per project convention. The release-cut workflow promotes it to a version heading at tag time.

**Validation**:
- `[Unreleased]` section updated
- No version heading added
- Both `Closes #1512` and `Closes #1536` appear (SC-007)
- "Added" and "Fixed" subsections used appropriately

## Definition of Done

- [ ] `CLAUDE.md` has "Adding a schedule-entries class" checklist with 7 steps
- [ ] `docs/specs/operations-playbooks.md` has `schedule.disabled_entries` section with YAML examples and warnings
- [ ] `ScheduleListHandler` groups output by owning class FQCN; disabled entries show `[disabled]`
- [ ] `CHANGELOG.md` `[Unreleased]` has Added + Fixed entries mentioning both issue closures
- [ ] PR description includes `Closes #1512` and `Closes #1536` (C-003)
- [ ] `composer verify` green (SC-006)
- [ ] Full CI green (C-007, C-008)

## Risks

| Risk | Mitigation |
|---|---|
| `ScheduleListHandler` has snapshot tests that assert exact output | Find and update them to match new grouped format |
| Task-to-owning-class association is complex | Use the simplest association approach (option 3 or registry map); don't add `ownerClass` to `ScheduledTask` |
| `CLAUDE.md` becomes too long | The new checklist is ~10 lines; acceptable addition |
| `Closes #N` in commit footer auto-closes issues if used in a regular commit (not just PR merge) | Use `Refs #N` in intermediate commits; use `Closes #N` only in the final PR merge commit body |

## Reviewer Guidance

- Verify `ScheduleListHandler` output is readable and groups correctly for both active and disabled entries
- Confirm `CHANGELOG.md` uses `[Unreleased]` only (no new version heading)
- Confirm `Closes #1512` and `Closes #1536` appear in the merge commit or PR body
- Check `CLAUDE.md` checklist mentions `@api` requirement (dead-code gate)
- Verify `composer verify` is explicitly run and passes before filing PR

## Activity Log

- 2026-05-21T01:25:39Z – claude:sonnet:implementer:implementer – shell_pid=825011 – Started implementation via action command
- 2026-05-21T01:31:03Z – claude:sonnet:implementer:implementer – shell_pid=825011 – CLAUDE.md schedule-entries checklist + ops playbook disabled_entries doc + CHANGELOG bullets (Refs #1512 #1536). All tests pass (643), cs-check clean, phpstan clean.
- 2026-05-21T01:37:25Z – claude:opus-4-7:reviewer:reviewer – shell_pid=829845 – Started review via action command
