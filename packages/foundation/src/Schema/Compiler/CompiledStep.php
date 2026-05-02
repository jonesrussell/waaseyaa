<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler;

/**
 * One executable step in a {@see CompiledMigrationPlan}.
 *
 * Steps are produced by platform-specific compilers (e.g.
 * {@see \Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCompiler}) and
 * consumed by the migrator's executor. They are narrow, immutable DTOs:
 * the platform compiler is the only thing that constructs them, and the
 * executor is the only thing that runs them. Callers never assemble SQL
 * by hand and feed it through this layer.
 *
 * **Identity contract:**
 *
 * - {@see kind()} returns a stable snake_case discriminator. The set of
 *   kinds is open (new platforms may add new kinds) but a given kind name
 *   is part of the public contract — once shipped, do not rename.
 * - {@see sql()} returns the SQL the executor will issue verbatim.
 *   Identifiers are already quoted; values are already escaped.
 * - {@see toCanonical()} returns the dictionary that participates in the
 *   compiled-plan SHA-256 (`diff_hash`). It MUST include `kind` and `sql`
 *   plus any structured fields a verify-mode reader needs to interpret
 *   the step without re-parsing SQL.
 *
 * PHP 8.4 has no `sealed` keyword. The closed set per platform is
 * enforced socially (one implementation per kind) and structurally (the
 * compiler folds steps through their canonical dict, never their class).
 */
interface CompiledStep
{
    /**
     * Stable snake_case discriminator. Once shipped, never rename.
     */
    public function kind(): string;

    /**
     * Platform-specific SQL the executor will issue verbatim.
     */
    public function sql(): string;

    /**
     * Canonical-JSON-ready representation, fed into the plan's diff_hash.
     *
     * Implementations MUST include at minimum `kind` and `sql`. Additional
     * structured fields (table, column, index name, ...) are encouraged
     * for verify-mode readability but participate in the hash, so adding
     * one to a shipped step is a breaking change to the diff_hash contract.
     *
     * @return array<string, mixed>
     */
    public function toCanonical(): array;
}
