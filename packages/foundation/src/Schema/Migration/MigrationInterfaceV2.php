<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Migration;

/**
 * Authoring contract for v2 migrations.
 *
 * Every v2 migration is a value object: it returns its identity, its
 * cross-migration ordering, and its structural intent. There is no
 * `up()` / `down()`, no `SchemaBuilder` callback, no DB I/O. Application
 * is the {@see \Waaseyaa\Foundation\Migration\Migrator}'s job (WP06); the
 * compiler emits SQL from the diff (WP04) — neither lives on the
 * migration class itself.
 *
 * Implementations MUST be `final readonly class`. There is no abstract
 * base — the contract is small enough that boilerplate is cheaper than
 * indirection, and `final readonly` is load-bearing for plan identity
 * (the {@see MigrationPlan::checksum()} is only stable when the plan is
 * truly immutable after construction).
 *
 * **Coexistence.** v2 migrations live alongside the legacy
 * {@see \Waaseyaa\Foundation\Migration\Migration} interface; both populate
 * the same migrations ledger via the unified DAG (see §15 Q4 and WP06).
 *
 * @see docs/specs/schema-evolution-v2.md §4
 */
interface MigrationInterfaceV2
{
    /**
     * Stable ledger key for this migration.
     *
     * Format is locked by §15 Q1: `{vendor}/{package}:v2:{kebab-slug}`.
     * Example: `waaseyaa/groups:v2:add-archived-flag`.
     *
     * Returned as a `string` (not {@see MigrationId}) because the legacy
     * `migration` ledger column is a string and the unified DAG operates
     * on string keys. Factories SHOULD construct via
     * `MigrationId::fromString(...)->toString()` so validation runs at
     * authoring time rather than ledger-read time.
     */
    public function migrationId(): string;

    /**
     * Composer package this migration belongs to.
     *
     * Used by the unified DAG (WP06) and Q4's tie-break
     * `(package ASC, migration ASC)` for deterministic ordering when
     * dependency graph edges leave a tie.
     */
    public function package(): string;

    /**
     * Cross-migration ordering edges.
     *
     * Each entry is either:
     * - a fully-qualified `migration_id` (e.g. `waaseyaa/groups:v2:add-archived-flag`)
     *   meaning "wait until that exact migration has applied"; or
     * - a composer package name (e.g. `waaseyaa/entity-storage`) meaning
     *   "wait until **any** v2 migration in that package has applied".
     *
     * Package-level edges match today's `$after` semantics scoped to v2
     * nodes. WP06 resolves these strings into DAG edges and detects
     * unknown references; this contract only locks the data shape.
     *
     * @return list<string>
     */
    public function dependencies(): array;

    /**
     * Structural intent of this migration as a {@see MigrationPlan}.
     *
     * The plan wraps a root {@see \Waaseyaa\Foundation\Schema\Diff\CompositeDiff}.
     * An empty plan ({@see MigrationPlan::isEmpty()}) is a valid result —
     * the Migrator must treat it as a successful no-op apply and write
     * the ledger row (per Q3 / WP06).
     */
    public function plan(): MigrationPlan;
}
