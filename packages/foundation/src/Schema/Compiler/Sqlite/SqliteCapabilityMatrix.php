<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Sqlite;

/**
 * Static matrix of pre-tabulated SQLite version checkpoints.
 *
 * Codifies the version → capability mapping documented in
 * `docs/specs/sqlite-capability-matrix.md`. The matrix exists alongside
 * {@see SqliteCapabilities::forVersion()} for two reasons:
 *
 * 1. **Stable test fixtures.** Tests that need "the 3.25 capability set"
 *    can call `SqliteCapabilityMatrix::sqlite325()` and survive future
 *    additions to the capability struct without rewriting every call.
 * 2. **Documentation in code.** The set of named factories is the
 *    canonical list of "version checkpoints we care about" — drift
 *    between this class and the spec doc is a code smell.
 *
 * **Checkpoints (locked):**
 *
 * | Method        | Version   | Notable change                          |
 * |---------------|-----------|------------------------------------------|
 * | `sqlite30()`  | `3.0.0`   | Baseline. RENAME TO supported.           |
 * | `sqlite321()` | `3.21.0`  | Pre-rename-column. Most legacy installs. |
 * | `sqlite325()` | `3.25.0`  | RENAME COLUMN added.                     |
 * | `sqlite335()` | `3.35.0`  | DROP COLUMN added.                       |
 * | `sqlite340()` | `3.40.0`  | Stable mid-2022 baseline; CI default.    |
 * | `sqlite350()` | `3.50.0`  | Current upstream as of mission #529.     |
 *
 * Use {@see for()} when the caller has an arbitrary version string from
 * `sqlite_version()`; use a checkpoint when authoring fixtures.
 */
final readonly class SqliteCapabilityMatrix
{
    public static function sqlite30(): SqliteCapabilities
    {
        return SqliteCapabilities::forVersion('3.0.0');
    }

    public static function sqlite321(): SqliteCapabilities
    {
        return SqliteCapabilities::forVersion('3.21.0');
    }

    public static function sqlite325(): SqliteCapabilities
    {
        return SqliteCapabilities::forVersion('3.25.0');
    }

    public static function sqlite335(): SqliteCapabilities
    {
        return SqliteCapabilities::forVersion('3.35.0');
    }

    public static function sqlite340(): SqliteCapabilities
    {
        return SqliteCapabilities::forVersion('3.40.0');
    }

    public static function sqlite350(): SqliteCapabilities
    {
        return SqliteCapabilities::forVersion('3.50.0');
    }

    /**
     * Generic factory delegating to {@see SqliteCapabilities::forVersion()}
     * for runtimes whose version string came from a live connection
     * rather than a documented checkpoint.
     */
    public static function for(string $version): SqliteCapabilities
    {
        return SqliteCapabilities::forVersion($version);
    }
}
