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
 * - `supportsDropColumn` — true on SQLite ≥ 3.35 (added 2021-03-12).
 *   Informational in v1: the destructive-allowed path emits the naive
 *   `ALTER TABLE … DROP COLUMN` SQL regardless and lets older runtimes
 *   raise their own error at apply time (per WP05 risk note: "you're on
 *   your own for SQLite-version compatibility once you opt in to
 *   destruction"). Verify mode (WP10) and future ADRs may consult this
 *   flag to gate at compile time.
 * - `foreignKeysEnabled` — informational. v1 of the SQLite compiler
 *   rejects every FK op via {@see \Waaseyaa\Foundation\Schema\Compiler\Validation\ForeignKeyUnsupportedException}
 *   (Q6). The flag exists so future WPs can branch on it without an
 *   API change.
 *
 * Use {@see forVersion()} for the common "I have a version string"
 * case; the constructor is provided for tests and explicit overrides.
 * For pre-tabulated checkpoints (`sqlite325()`, `sqlite340()`, …) see
 * {@see SqliteCapabilityMatrix}.
 */
final readonly class SqliteCapabilities
{
    public function __construct(
        public string $version,
        public bool $supportsRenameColumn,
        public bool $foreignKeysEnabled = false,
        public bool $supportsDropColumn = false,
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
            foreignKeysEnabled: false,
            supportsDropColumn: version_compare($version, '3.35.0', '>='),
        );
    }
}
