<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Sqlite;

/**
 * Declared capabilities for a target SQLite runtime.
 *
 * The compiler is a pure function of (diff, capabilities). Capabilities
 * are explicit so the same compiler version produces the same plan for
 * the same input on every developer machine — no hidden coupling to a
 * connected SQLite version.
 *
 * **Versioned capability flags:**
 *
 * - `supportsRenameColumn` — true on SQLite ≥ 3.25 (added 2018-09-15).
 *   Older runtimes must use the drop+add escape hatch with explicit
 *   data migration (out of scope for v1).
 * - `foreignKeysEnabled` — informational. v1 of the SQLite compiler
 *   does NOT emit FK ops (WP05 gates `AddForeignKey` /
 *   `DropForeignKey` with `FOREIGN_KEY_UNSUPPORTED_SQLITE_V1`). The
 *   flag exists so future WPs can branch on it without an API change.
 *
 * Use {@see forVersion()} for the common "I have a version string"
 * case; the constructor is provided for tests and explicit overrides.
 */
final readonly class SqliteCapabilities
{
    public function __construct(
        public string $version,
        public bool $supportsRenameColumn,
        public bool $foreignKeysEnabled = false,
    ) {}

    /**
     * Derive capabilities from a SQLite version string (e.g. `3.40.0`).
     *
     * Uses {@see version_compare()}'s semantics — pre-release suffixes
     * (`3.25.0-beta`) compare lower than the bare version, which matches
     * SQLite's own ordering.
     */
    public static function forVersion(string $version): self
    {
        return new self(
            version: $version,
            supportsRenameColumn: version_compare($version, '3.25.0', '>='),
        );
    }
}
