# Contract — CLI Runner (`import:*`)

**Mission:** `migration-platform-v1-01KRCDE9` (M-002)
**Spec sections:** §3.5, §3.6, §3.10 (FR-032..FR-040, FR-043, FR-044, FR-046, FR-047, FR-048, FR-061, FR-062), §9.
**Owning WPs:** WP06 (run + run-all + status + dry-run), WP07 (resume), WP08 (rollback + reset), WP09 (concurrency lock).
**Charter anchor:** §5.8 (new — Migration platform).

This document is the normative contract for the six CLI commands. Operators invoke these via `bin/waaseyaa`.

---

## Command index

| Command | Purpose | FR | WP |
|---|---|---|---|
| `import:run <migration-id>` | Execute a single migration | FR-032, FR-039, FR-040 | WP06 |
| `import:run-all` | Execute all registered migrations in dependency order | FR-033, FR-039, FR-040 | WP06 |
| `import:status [<migration-id>]` | Show per-migration state | FR-034 | WP06 |
| `import:rollback <migration-id>` | Undo a migration via reverse-creation rollback | FR-035, FR-043, FR-044 | WP08 |
| `import:reset <migration-id>` | Clear id-map for a migration (keeps destination entities) | FR-036 | WP08 |
| `import:resume <migration-id>` | Continue an interrupted run | FR-037, FR-038 | WP07 |

---

## `import:run <migration-id>` (FR-032)

```
bin/waaseyaa import:run <migration-id> [options]

Arguments:
  <migration-id>        Required. Must exist in registry; else exit 2.

Options:
  --dry-run             Execute source + process steps; skip destination writes.       (FR-039)
  --halt-on-error       Halt on first per-record error.                                 (FR-047)
  --limit=<N>           Process only the first N records.                               (FR-040)
  --batch-size=<N>      Commit every N records (1 ≤ N ≤ 100). Default: 1 (per-record).  (FR-038)
  --run-id=<uuid>       Override the generated run-id (advanced; CI/testing).
  --account=<id>        Account UID used for access checks. Defaults to system account.
```

### Execution sequence

1. Resolve registry; validate `<migration-id>` exists. Missing → exit 2 with `Migration not found: <id>` on stderr.
2. Acquire filesystem lock at `storage/migration-locks/<migration-id>.lock` (FR-061). Lock collision → exit 3 with `MigrationConcurrencyException` payload (lockPath + PID) on stderr.
3. Generate `run_id` as UUIDv7 (or use `--run-id` override).
4. Iterate `$source->records()`.
5. For each record:
   a. Compute `SourceId` via `$source->sourceIdFor($record)`.
   b. Compute `source_record_hash` from the record's canonical form.
   c. Lookup id-map → if exists and hash unchanged, skip (FR-031); mark in run-state as `'skipped'`.
   d. Run process map (chains in array order; output of N → input of N+1).
   e. Build `DestinationRecord`.
   f. Unless `--dry-run`: call `$destination->write($destRecord)`.
   g. UPSERT `migration_run_state` row with `item_status` + `position`.
6. Release lock (also on `SIGTERM`/`SIGINT` via `pcntl_signal` handler where available; FR-062).

### Exit codes

| Exit code | Meaning |
|---|---|
| 0 | Full success; all records processed (write or skip), zero errors. |
| 1 | Partial: ≥1 per-record error recorded; no run-level halt. |
| 2 | Usage error (migration id missing, invalid flag, etc.). |
| 3 | Concurrency lock collision (`MigrationConcurrencyException`). |
| 4 | Error-rate halt threshold crossed (`MigrationAbortedException`; Q5 resolution). |
| 5 | Run-level fatal: cycle, missing dep, source/destination plugin crash, id-map corruption (FR-048). |

### Output

Per-record progress line on stdout (one per N records where N is configurable via `--progress-every`, default 100):

```
[wp_posts_to_teachings] 1247 / 5000 ... 0 err, 0 skipped, run=01J5T2... eta=3m12s
```

Final summary on stdout:

```
== import:run wp_posts_to_teachings ==
total:     5000
imported:  4998
skipped:   2          (unchanged source_record_hash)
failed:    0
elapsed:   4m17s
throughput: 1166 records/min
exit:      0
```

---

## `import:run-all` (FR-033)

```
bin/waaseyaa import:run-all [options]
```

Same options as `import:run` (except `<migration-id>`). Runs every registered migration in topological-sort order. A migration's failure (per-record errors below halt threshold) does NOT halt the next migration; a run-level failure DOES (FR-048).

Exit codes: 0 if every migration exits 0; 1 if any exit 1; 4 if any exit 4; 5 if any exit 5. (Highest code wins.)

---

## `import:status [<migration-id>]` (FR-034)

```
bin/waaseyaa import:status              # all migrations
bin/waaseyaa import:status <migration-id>   # single migration
```

Output (tabular):

```
ID                              STATE       TOTAL  IMPORTED  FAILED  SKIPPED  LAST RUN
wp_users_to_accounts            complete    1500   1500      0       0        2026-05-11 14:22:07
wp_posts_to_teachings           partial     5000   3217      4       0        2026-05-11 14:35:51
wp_comments_to_engagement       pending     -      -         -       -        -
```

States:

- `pending` — never run (no `migration_run_state` rows).
- `running` — a lock is currently held.
- `partial` — last run did not visit every source record (interrupted; resumable).
- `complete` — last run processed every source record with zero per-record errors.
- `failed` — last run exited with code ≥4 (error-rate halt or run-level fatal).

The state is computed from `migration_run_state` aggregates, not stored separately. Operators can ask `--format=json` for machine-readable output.

---

## `import:rollback <migration-id>` (FR-035, FR-043, FR-044)

```
bin/waaseyaa import:rollback <migration-id> [--halt-on-error] [--account=<id>]
```

### Execution

1. Acquire filesystem lock (same lock file as `import:run`).
2. Walk `migration_id_map` rows for `<migration-id>` in reverse-creation order: `ORDER BY last_imported_at DESC, last_run_id DESC` (R4 mitigation).
3. For each row: call `$destination->rollback($writeResult)`. Errors logged on `entity.lifecycle` channel; walk continues (FR-044).
4. DELETE each id-map row inside its own transaction with the entity-delete.
5. Release lock.

Cross-migration rollback ordering is operator concern (plan Complexity Tracking #4): `import:rollback` operates per-migration. Operators wanting full mission rollback invoke it in reverse-dependency order.

### Exit codes

| Exit code | Meaning |
|---|---|
| 0 | Every id-map row walked; ≥0 may have been logged best-effort failures. |
| 2 | Usage error. |
| 3 | Concurrency lock collision. |
| 5 | Run-level fatal during walk (e.g. id-map table missing). |

---

## `import:reset <migration-id>` (FR-036)

```
bin/waaseyaa import:reset <migration-id> [--yes]
```

DELETEs all `migration_id_map` rows for `<migration-id>`. Does NOT delete destination entities — those remain in storage. Subsequent `import:run` re-imports the same source records as new entities (new uuids).

`--yes` skips the interactive confirmation prompt. Required in non-TTY contexts (CI).

### Use cases

- Re-importing from scratch after a schema change to the destination entity type.
- Recovering from a botched migration whose entities were already manually corrected (operator chose to keep the corrected entities and forget the id-map).

### Exit codes

| Exit code | Meaning |
|---|---|
| 0 | Reset complete. |
| 2 | Usage error or unconfirmed in TTY. |
| 5 | Database error during DELETE. |

---

## `import:resume <migration-id>` (FR-037, FR-038)

```
bin/waaseyaa import:resume <migration-id> [--run-id=<uuid>] [--halt-on-error] [--limit=<N>] [--batch-size=<N>]
```

Continues an interrupted run. Reads `migration_run_state` for `<migration-id>`; selects the most-recent `run_id` (or the explicit `--run-id`); computes `MAX(position) WHERE item_status = 'success'` as the resume point. Re-iterates `$source->records()` (re-entrancy contract from contracts/source-plugin.md C6) and skips records whose `source_id_hash` already has a `'success'` row for this run.

If no `migration_run_state` rows exist for `<migration-id>` → exit 2 with `No interrupted run found for <id>; use import:run instead`.

### Exit codes

Same as `import:run`.

---

## Concurrency lock contract (FR-061, FR-062)

Lock file: `storage/migration-locks/<migration-id>.lock`. Contents: holding PID, ASCII-decimal, newline-terminated.

| Operation | Behavior |
|---|---|
| Acquire | `flock(LOCK_EX | LOCK_NB)`. Failure → read PID from file, raise `MigrationConcurrencyException(lockPath, pid)`. |
| Release | Close handle, `unlink()` the file. Idempotent. |
| Signal handler | `pcntl_signal(SIGTERM, ...)` and `(SIGINT, ...)` — calls release(), re-raises. Skipped on platforms without `pcntl` (Windows). |
| Stale recovery | NOT automatic. `MigrationConcurrencyException::getMessage()` includes the lock file path and held PID; operators verify the PID is dead, then `rm` the lock file. Documented in FR-062 and quickstart §C. |

Held by every command that mutates id-map / run-state for a migration: `import:run`, `import:run-all` (per-migration scope inside the all-loop), `import:rollback`, `import:reset`, `import:resume`. NOT held by `import:status` (read-only).

---

## Error model (FR-046, FR-047, FR-048)

Per-record errors recorded in `migration_run_state.error_code` + `error_message`; runner increments error counter; default-continue; `--halt-on-error` overrides to halt-on-first.

Error-rate halt (Q5 resolution): if `errors / processed > $migration->errorRateHalt`, the runner raises `MigrationAbortedException` (exit code 4) regardless of `--halt-on-error`. Warn threshold (`errorRateWarn`) emits a structured warning on `migration.deprecation` channel but does not halt.

Run-level fatal errors (id-map corruption, cycle, missing dep, source/destination plugin crash) always halt (exit code 5) regardless of `--halt-on-error`.

---

## Out of scope (FR boundaries)

- **Distributed execution.** A migration runs in a single OS process. Sharded execution across hosts is out of scope.
- **Live progress dashboards.** `import:status` is a snapshot. Streaming progress to a UI is out of scope.
- **`migrate:*` namespace.** D12 resolution: stay on `import:*`. The schema-migration `migrate:*` namespace is separate and unaffected.
- **Pause / inspect / debug.** No `import:pause` command in v0.x. Operators `kill -TERM` the run, inspect, then `import:resume`.
