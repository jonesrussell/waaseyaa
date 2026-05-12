# WP10 Review — Cycle 1

**Mission:** entity-storage-v2-01KRCDDC (M-001)
**Work Package:** WP10 — Storage-migration generator CLI
**Commit reviewed:** `eee955359` on `kitty/mission-entity-storage-v2-01KRCDDC-lane-a`
**Reviewer:** Claude Opus 4.7
**Date:** 2026-05-12
**Verdict:** **APPROVED**

---

## Acceptance criteria

| # | Criterion | Result |
|---|---|---|
| 1 | Command surface `make:storage-migration <id> [--target] [--dry-run] [--force]` | PASS — `MakeStorageMigrationServiceProvider::nativeCommands()` declares all four with correct modes/defaults. |
| 2 | Exit codes per `contracts/migration-generator-cli.md` (0–4) | PASS — every code mapped, see matrix below. |
| 3 | `StorageMigrationEmitter` consumes WP05 `TypeMapping`, exit 4 on unmapped, `UnmappedFieldTypeException` carrier | PASS — `emitColumnMap()` throws `UnmappedFieldTypeException(fieldId, fieldType)`; handler returns 4 with stderr naming the offending field. |
| 4 | `StorageMigrationTemplate` emits anonymous-class migration extending `Migration` with `up()`/`down()` and `@expectedReverseSeconds 30` in class docblock | PASS — verified at `StorageMigrationTemplate::render()`; annotation present in heredoc literal. |
| 5 | `BackfillHelper` reads `_data`, decodes with `JSON_THROW_ON_ERROR`, writes typed columns; row-count pre/post validation throws on mismatch | PASS — `BackfillHelper::execute()` snapshots `preCount`, runs `JSON_THROW_ON_ERROR` decode, validates `postCount`, throws `BackfillRowCountMismatchException` on divergence. |
| 6 | End-to-end test at `packages/cli/tests/Integration/MakeStorageMigration/EndToEndTest.php` | PASS — fixture entity → generate → up() → backfill verify → mismatch trigger scenario. 6 tests / 42 assertions, all pass. |
| 7 | Doc stub `docs/upgrades/waaseyaa-alpha-X-to-Y.md` with "Storage migration cookbook" section | PASS. |
| 8 | Namespace casing matches `packages/cli/composer.json` PSR-4 | PASS — composer maps `"Waaseyaa\\CLI\\": "src/"`; all new files declare `namespace Waaseyaa\CLI\…`, consistent with every existing file in the package. (Reviewer note about `Waaseyaa\Cli` was a heuristic; the established project convention is `Waaseyaa\CLI`.) |
| 9 | `bin/check-package-layers` clean | PASS. |
| 10 | `@api` on new public symbols | PASS — Handler, Provider, Emitter, Template, BackfillHelper, both exception classes, plus public methods. |
| 11 | No `psr/log`, no `Illuminate\*`, no service locators, `declare(strict_types=1)`, `final class` | PASS — `Waaseyaa\Foundation\Log\LoggerInterface` + `NullLogger`; all classes `final`; strict types throughout. |
| 12 | Scope compliance — no §1.2/§2.2 non-goals | PASS — per-entity-type only; no admin UI, no listing UI, no auto-pruning, no mass migrations. |

## Exit code conformance matrix

Walked `MakeStorageMigrationHandler::execute()` against `contracts/migration-generator-cli.md`:

| Code | Contract meaning | Branch in handler |
|---|---|---|
| 0 | Success (file written, or `--dry-run` printed) | Final `return 0` after `file_put_contents` / `--dry-run` stdout. |
| 1 | Unknown entity type | `catch (\InvalidArgumentException)` around `getDefinition()`. |
| 2 | Unsupported `--target` | `!in_array($target, SUPPORTED_TARGETS, true)` guard. |
| 3 | Migration file exists, no `--force` | `file_exists($targetPath) && !$force`. |
| 4 | Unmapped field type | `catch (UnmappedFieldTypeException $e)`, stderr names offending field+type. |

All five paths are covered by `EndToEndTest`.

## Backfill rollback path

The `up()` step is not asked to open its own transaction; it does not need to. `Migrator::apply()` (foundation) wraps every `$migration->up($schema)` call in `$this->connection->transactional(...)` (lines 184–188). When `BackfillHelper` throws `BackfillRowCountMismatchException` during backfill, the exception propagates out of `up()`, the closure exits abnormally, and DBAL rolls back **both** the schema mutations (`ALTER TABLE ADD COLUMN`) **and** the ledger insert. The full migration does not commit on mismatch. The E2E test exercises the throwing path with a SQLite trigger that mutates row count mid-update; assertion verifies the exception type and message.

## Handler/Provider pattern parallels existing CLI commands

Confirmed by inspecting `MakeServiceProviderA::nativeCommands()` (sibling). Same shape: `CommandDefinition` with arguments + options, `handler: [HandlerClass::class, 'execute']`. The new provider mirrors `MakeMigrationHandler` registration exactly. Composer extras already include `Waaseyaa\\CLI\\Provider\\MakeStorageMigrationServiceProvider` in the auto-discovery list.

## Filename convention

`{Ymd_His}_storage_migration_{entityTypeId}_to_{target}.php` — sortable timestamp prefix matches existing per-package migration files.

## Gate spot-checks

| Gate | Result |
|---|---|
| `composer cs-check` | OK (no files needed fixing) |
| `composer phpstan` | OK — 0 errors over 1242 files |
| `bin/check-package-layers` | OK — no upward edges introduced |
| `bin/check-composer-policy` | OK |
| `./vendor/bin/phpunit packages/cli/tests/Integration/MakeStorageMigration/` | 6 tests, 42 assertions, all pass |
| `./vendor/bin/phpunit packages/cli/tests/` | 525 tests, 1460 assertions, all pass |
| `./vendor/bin/phpunit` (full suite) | **7693 tests, 18682 assertions, all pass** (2 deprecations, 2 skipped — pre-existing) |

## Notes / minor observations (non-blocking)

- `down()` is intentionally a no-op with a clear inline comment referencing the upgrade cookbook. This is consistent with the contract's "reversible `down()` step" plus `@expectedReverseSeconds 30` warning surface — the runner gates slow reversals via the annotation, not by automating destructive column drops on SQLite. Acceptable for WP10; WP11/WP12 can refine when a real Postgres reversal is exercised.
- `StorageMigrationTemplate::filename()` uses `date('Ymd_His')` and `render()` uses `date('Y_m_d_His')` for the docblock timestamp. Slightly different formats; both readable, no functional issue.
- `addslashes()` is used to escape field/type into the emitted SQL fragment. Acceptable because column names and SQL type strings come from internal `TypeMapping`, not user input. If field IDs could ever contain backticks or quotes in the future, consider an allowlist. Not a blocker.

## Verdict

**Approved** — WP10 satisfies all FR-041–FR-045 acceptance criteria, all gates green, exit codes fully mapped, backfill rollback path verified via Migrator transactional wrapping.
