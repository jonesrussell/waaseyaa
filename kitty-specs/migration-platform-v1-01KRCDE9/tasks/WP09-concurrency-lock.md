---
work_package_id: WP09
title: Concurrency lock + MigrationConcurrencyException
dependencies:
- WP06
requirement_refs:
- FR-061
- FR-062
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T049
- T050
- T051
- T052
agent: "claude:opus:waaseyaa-implementer:implementer"
shell_pid: "14193"
history:
- timestamp: '2026-05-13T02:27:32Z'
  actor: spec-kitty.tasks
  event: wp_created
  notes: Generated as part of M-002 task materialization.
authoritative_surface: packages/migration/src/Runner/MigrationLock.php
execution_mode: code_change
mission_id: 01KRCDE9ZXK2JEFPT6THSBVKNY
mission_slug: migration-platform-v1-01KRCDE9
owned_files:
- packages/migration/src/Runner/MigrationLock.php
- packages/migration/src/Exception/MigrationConcurrencyException.php
- packages/migration/tests/Unit/Runner/MigrationLockTest.php
- packages/migration/tests/Integration/MigrationLockIntegrationTest.php
priority: p1
tags:
- stable-surface
- layer-3
- concurrency
---

# WP09 — Concurrency lock + MigrationConcurrencyException

## Objective

Prevent two concurrent `import:run` (or `import:resume` / `import:rollback`) invocations against the same migration via a filesystem lock. Surface clean, operator-actionable errors when the lock is contested. Document the manual stale-lock recovery path.

## Dependencies

- Internal: WP06 (the CLI commands that need to acquire the lock).
- External: None. Uses standard PHP `flock()`. Optionally uses `pcntl_signal()` for graceful release on `SIGTERM` / `SIGINT` when the extension is available.
- Charter anchors: §5.8 (proposed) — `MigrationLock`, `MigrationConcurrencyException`.

## Scope (in / out)

**In scope**
- `MigrationLock` class wrapping the filesystem lock acquire/release lifecycle (FR-061).
- Lock file path: `storage/migration-locks/<migration-id>.lock` (per spec §9.3, decision D11).
- PID inside the lock file; readable by operators inspecting stale locks.
- `MigrationConcurrencyException` typed exception carrying lock-file path + holding PID (FR-061).
- `pcntl_signal()`-based graceful release on SIGTERM/SIGINT (FR-062) when the extension is available; documented Windows degradation.
- Wiring into `ImportRunCommand` / `ImportRunAllCommand` / `ImportResumeCommand` / `ImportRollbackCommand` / `ImportResetCommand` from WP06 + WP07 + WP08 — acquire the lock at command start; release on exit.
- Documentation of the manual recovery path (stale lock = operator deletes the file).

**Out of scope**
- Auto stale-lock recovery (research §2 D11: explicitly rejected — PID reuse risk).
- Distributed locks across hosts (filesystem-local only, by design).
- Lock files for `import:status` (read-only command; no lock needed).

## Branch strategy

Planning/base branch: `main`. Merge target: `main`. Per-lane worktree. Run `spec-kitty agent action implement WP09 --agent opus`.

## Implementation guidance

### Subtask T049 — `MigrationLock` class

**Purpose**: The lock abstraction. Single API; works across SAPIs.

**FRs covered**: FR-061, FR-062.

**Files**:
- `packages/migration/src/Runner/MigrationLock.php` (new, ~280 lines).

**Steps**:
1. `final class MigrationLock` (`@api`).
2. Constructor: `__construct(string $migrationId, string $lockDir, LoggerInterface $logger)`. Validates `$migrationId` matches `/^[a-z][a-z0-9_]*$/` (defensive — should already be enforced upstream). `$lockDir` is the absolute path to `storage/migration-locks/`; the constructor creates it (`mkdir(..., 0o755, recursive: true)`) if it doesn't exist.
3. Path computation: `$this->lockPath = $lockDir . '/' . $migrationId . '.lock'`.
4. `acquire(): void`:
   - Open the lock file with `fopen($lockPath, 'c')`. The `'c'` mode creates if missing, opens for writing without truncating — leaves the existing PID readable if locked.
   - Try `flock($handle, LOCK_EX | LOCK_NB)`. If false:
     - Read the existing PID from the file content (best-effort; may be empty if a prior run never wrote it).
     - Raise `MigrationConcurrencyException` carrying the lock path + (parsed-int-or-null) PID.
   - On success: truncate the file, write `getmypid()` + newline, `fflush()` the handle (do NOT release the lock yet).
   - Store the handle on the instance.
5. `release(): void`:
   - `flock($handle, LOCK_UN)`, `fclose($handle)`, `@unlink($lockPath)`. The unlink is best-effort — another process may have grabbed the path; on Windows, deletion of an open file silently fails.
6. `installSignalHandlers(): void` (called from `acquire()` if `function_exists('pcntl_signal')`):
   - Register `pcntl_signal(SIGTERM, fn() => $this->release())`.
   - Register `pcntl_signal(SIGINT, fn() => $this->release())`.
   - Call `pcntl_async_signals(true)` to enable signal dispatch between PHP statements.
   - On Windows (no `pcntl_*`), log at info level: "pcntl extension missing; lock will release on normal exit only" and proceed.
7. Register a `register_shutdown_function([$this, 'release'])` in `acquire()` so the lock releases even if the script exits abnormally (without pcntl).
8. Helper `pid(): ?int` returns the PID currently holding the lock (reads `$lockPath`'s content). Used by exception messages and operator tooling.

**Validation**:
- [ ] Unit test: acquire + release on a temp directory.
- [ ] Unit test: second acquire on the same migration_id raises `MigrationConcurrencyException`.
- [ ] Unit test: PID written into the lock file matches `getmypid()`.
- [ ] Unit test: `release()` is idempotent (calling twice is safe).
- [ ] Unit test: missing `$lockDir` is created by the constructor.

**Edge cases**:
- `flock()` on NFS is unreliable; the framework documents that the lock directory should be on a local filesystem (or on a filesystem that honours POSIX locking). Add a startup warning when the lock dir's mount type can't be verified (skip on Windows).
- Race condition between PID-check and lock-acquire on stale locks: documented behavior — operators delete stale locks manually. Two operators racing to delete the same stale lock will both succeed (one removes, one no-ops).

### Subtask T050 — `MigrationConcurrencyException`

**Purpose**: Typed exception with operator-actionable payload.

**FRs covered**: FR-061, FR-062 (recovery path documentation).

**Files**:
- `packages/migration/src/Exception/MigrationConcurrencyException.php` (new, ~60 lines).

**Steps**:
1. Extends `\RuntimeException`. `@api`. Public readonly `string $migrationId`, `string $lockPath`, `?int $holdingPid`. Stable `public const CODE = 'MIGRATION_CONCURRENT_RUN'`.
2. Message format:
   ```
   Migration '<id>' is already running (lock: <lockPath>, pid: <holdingPid>).
   If the holding process is no longer alive, manually remove the lock file:
       rm <lockPath>
   ```
   When `$holdingPid === null`, replace `pid: <holdingPid>` with `pid: unknown`.

**Validation**:
- [ ] Round-trip test.
- [ ] Message contains both the lock path and (parsed) PID.

### Subtask T051 — Wire lock into CLI commands

**Purpose**: All mutating `import:*` commands acquire the lock before doing work and release it on exit.

**FRs covered**: FR-061 (composition).

**Files**:
- `packages/cli/src/Command/Import/ImportRunCommand.php` (modify — WP06).
- `packages/cli/src/Command/Import/ImportRunAllCommand.php` (modify — WP06).
- `packages/cli/src/Command/Import/ImportResumeCommand.php` (modify — WP07).
- `packages/cli/src/Command/Import/ImportRollbackCommand.php` (modify — WP08).
- `packages/cli/src/Command/Import/ImportResetCommand.php` (modify — WP08).

**Steps**:
1. Inject `MigrationLockFactory` (or `MigrationLock` builder closure) into each command's constructor.
2. In `execute()`:
   - Build the lock for the target migration_id.
   - Wrap the existing body in a `try { $lock->acquire(); ... } finally { $lock->release(); }`.
   - Catch `MigrationConcurrencyException` → print the exception message to stderr → exit code 2 (per `contracts/cli-runner.md` "concurrent run" exit code).
3. `ImportRunAllCommand` is the trickiest case — it walks N migrations in sequence. Acquire the lock PER MIGRATION (acquire + release inside the loop), not once for the whole walk. A long run-all that locks every migration for hours would block operators. Document this in code comments and the WP12 source-reader-author guide.
4. `ImportStatusCommand` does NOT acquire a lock — read-only.

**Validation**:
- [ ] Unit test on `ImportRunCommand`: when `MigrationLock::acquire()` throws `MigrationConcurrencyException`, the command exits 2 and prints the exception message.
- [ ] Unit test on `ImportRunAllCommand`: lock is acquired and released for each migration; no global lock across the run-all walk.

**Edge cases**:
- A command that fails mid-execute (uncaught exception in the body) must still release the lock — verified via the `finally` block.
- Resume + rollback against the same migration in quick succession must work: each command holds the lock briefly, releases, then the next one acquires.

### Subtask T052 — Integration test: concurrent acquisition

**Purpose**: Prove the lock contract holds across two concurrent processes (or two PHP child processes within one test).

**FRs covered**: FR-061, FR-062.

**Files**:
- `packages/migration/tests/Integration/MigrationLockIntegrationTest.php` (new, ~200 lines).

**Steps**:
1. Test 1 — concurrent acquire raises:
   - Process A: `pcntl_fork()` (skip the test if pcntl absent; mark as skipped via `markTestSkipped()`).
   - Parent acquires the lock, waits.
   - Child attempts to acquire; asserts `MigrationConcurrencyException` is raised with the parent's PID.
   - Parent releases. Child re-attempts; succeeds.
2. Test 2 — graceful release on SIGTERM (pcntl required):
   - Fork a child; child acquires the lock and sleeps.
   - Parent reads the lock file's PID; asserts non-null.
   - Parent sends SIGTERM to the child.
   - Parent waits for child exit; reads lock file; asserts lock is released (file removed).
3. Test 3 — shutdown-function release (no pcntl required):
   - In a `pcntl_exec()`'d subprocess (or a separate `proc_open()` call), run a tiny PHP snippet that acquires a lock and then exits without explicit release.
   - After the subprocess exits, assert the lock file is gone.
4. Test 4 — Windows degradation:
   - Skip the pcntl-dependent assertions on Windows (`PHP_OS_FAMILY === 'Windows'`). Just acquire + release + verify file removal.

**Validation**:
- [ ] Tests 1–3 green on Linux + macOS.
- [ ] Test 4 covers Windows degradation cleanly (mark-skip rather than fail).
- [ ] Full suite green.

**Edge cases**:
- `pcntl_fork()` is not available in all PHP builds; the test must `markTestSkipped()` rather than fail.
- On macOS, `flock()` and POSIX advisory locks both work; on Linux, the framework uses `flock()` (BSD-style). Document.

## Tests

- **Unit**: T049 (`MigrationLockTest`), T050 (exception round-trip).
- **Integration**: T052 — four tests covering concurrent acquisition and signal handling.
- **Conformance**: not applicable.

## Definition of Done

- [ ] All four subtasks complete.
- [ ] FR-061 + FR-062 cited in code as `@spec FR-061` / `@spec FR-062`.
- [ ] `composer phpstan` clean.
- [ ] `composer cs-check` clean (run twice).
- [ ] `bin/check-package-layers` clean.
- [ ] `bin/check-composer-policy` clean.
- [ ] `bin/audit-dead-code` clean.
- [ ] `./vendor/bin/phpunit` full suite green.
- [ ] `MigrationLock` and `MigrationConcurrencyException` carry `@api`.
- [ ] Lock file path matches `storage/migration-locks/<migration-id>.lock`.
- [ ] Lock contains the PID after acquisition.
- [ ] All five mutating `import:*` commands acquire + release the lock around their work bodies.
- [ ] `import:status` does NOT acquire a lock (verified by reading the command).
- [ ] `import:run-all` acquires + releases per migration (not once globally) — verified by T051 test.
- [ ] Stale-lock recovery path documented in `MigrationConcurrencyException`'s message AND in the WP12 author guide.

## Risks

- **R1 — `flock()` on NFS unreliable**: documented; framework requires local-filesystem lock directories. Mitigate with a startup warning if the lock dir is on an unknown FS type (best-effort detection).
- **R2 — Signal handler not invoked between long-running PHP statements**: `pcntl_async_signals(true)` enables dispatch but only at safe points. A truly synchronous CPU-bound process can delay signal delivery. The shutdown-function fallback covers this. Document.
- **R3 — Windows-only deployments**: lose graceful-shutdown behavior. The framework continues to work; just less robust on operator interrupt. Documented degradation.
- **R4 — PID reuse race**: a process holding a lock dies abnormally; another process takes its PID; an operator runs `import:run`; the system thinks the new (unrelated) process is holding the lock. Mitigation: operators read the lock file's PID + the process command line (`ps -p <pid>`) to confirm before deleting. Documented in `MigrationConcurrencyException`'s message.

## Reviewer guidance

- Check: `MigrationLock::acquire()` uses `LOCK_EX | LOCK_NB` (non-blocking). Blocking acquisition is incorrect — it would create silent waits that confuse operators.
- Check: `release()` is idempotent.
- Check: `MigrationConcurrencyException` message includes both the lock path AND the holding PID (or `unknown` if missing).
- Check: `ImportRunAllCommand` acquires + releases per migration, not once.
- Check: `ImportStatusCommand` does NOT touch `MigrationLock`.
- Verify: T052 Test 1 actually forks (not mocks) — the test must exercise the OS lock primitive.
- Verify: lock file is removed after a normal exit.
- Verify: shutdown-function release covers the no-pcntl path.
- Confirm: stale-lock recovery is documented in the exception message AND will be documented in the WP12 author guide.

## Activity Log

- 2026-05-13T16:04:42Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=14193 – Started implementation via action command
- 2026-05-13T16:19:03Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=14193 – Ready for review — per-migration filesystem flock + MigrationConcurrencyException land; all five mutating import:* commands wrap acquire/release; import:status untouched; full phpunit 8223 tests pass
