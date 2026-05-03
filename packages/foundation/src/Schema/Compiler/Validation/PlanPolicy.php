<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Validation;

/**
 * Compile-time policy controlling which classes of structural change
 * the compiler will translate.
 *
 * Currently a single flag governs destructive ops. Future flags (e.g.
 * `allowImpliedDataMigration`, `maxStepCount`) will land here without
 * an API break — callers always construct via named arguments.
 *
 * **Default is conservative.** A bare `new PlanPolicy()` blocks every
 * destructive op. Operators must explicitly opt in to data loss with
 * `new PlanPolicy(allowDestructive: true)` per spec §11.
 *
 * Lives under `Validation/` (not `Sqlite/`) because the policy applies
 * uniformly across all platform compilers — the same `DropColumn` op
 * is destructive on SQLite, MySQL, and Postgres alike.
 */
final readonly class PlanPolicy
{
    public function __construct(
        public bool $allowDestructive = false,
    ) {}
}
