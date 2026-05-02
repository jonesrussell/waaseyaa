<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Migration;

use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;

/**
 * Immutable bundle of (metadata, root CompositeDiff) returned by
 * {@see MigrationInterfaceV2::plan()}.
 *
 * Per §15 Q3 (ratified 2026-05-02), the empty plan is
 * `new MigrationPlan(..., root: CompositeDiff::empty())` — there is no
 * `MigrationPlan::empty()` named factory. Call sites that mean "no work"
 * construct the plan with explicit metadata and an empty root so the
 * ledger key is still recorded.
 *
 * **Identity contract.**
 *
 * - {@see toCanonical()} mirrors the root's canonical shape exactly. It
 *   does NOT include `migrationId`, `package`, or `dependencies`. This is
 *   load-bearing: two distinct migrations whose structural intent is
 *   identical hash-equate, which is the property the unified ledger
 *   relies on for idempotency checks.
 * - {@see checksum()} is the SHA-256 of {@see CompositeDiff::toCanonicalJson()}
 *   of the root — i.e. it is exactly equal to `root->checksum()`.
 * - The `migration_id` is the ledger identity for **ordering**, not
 *   structural identity. Conflating the two breaks Q4's tie-break.
 *
 * **`diffHash()`** returns null in this WP. The compiled-plan hash is
 * the SHA-256 over the serialized compiled DTO list (WP04 output) and is
 * computed downstream of this value type — see §15 Q2.
 *
 * @see docs/specs/schema-evolution-v2.md §3, §15 Q1, Q2, Q3
 */
final readonly class MigrationPlan
{
    /**
     * @param list<string> $dependencies        migration_ids and/or composer
     *                                          package names; see
     *                                          {@see MigrationInterfaceV2::dependencies()}
     *                                          for the full semantics.
     * @param string|null  $compiledDiffHash    SHA-256 over the canonical
     *                                          serialized compiled plan
     *                                          (DTO list) — populated by
     *                                          WP04 when the compiler runs.
     *                                          In WP03 the field is always
     *                                          null at construction time;
     *                                          accessor is exposed via
     *                                          {@see diffHash()}.
     */
    public function __construct(
        public string $migrationId,
        public string $package,
        public array $dependencies,
        public CompositeDiff $root,
        public ?string $compiledDiffHash = null,
    ) {}

    /**
     * Whether the plan carries any structural change.
     *
     * Shorthand for `$this->root->isEmpty()`. An empty plan is still a
     * valid plan — the Migrator (WP06) records the ledger row but emits
     * no SQL.
     */
    public function isEmpty(): bool
    {
        return $this->root->isEmpty();
    }

    /**
     * Canonical-JSON-ready representation of the structural intent.
     *
     * Delegates to {@see CompositeDiff::toCanonical()}; metadata is not
     * included because it is not part of structural identity. See class
     * docblock for why.
     *
     * @return array{ops: list<array<string, mixed>>}
     */
    public function toCanonical(): array
    {
        return $this->root->toCanonical();
    }

    /**
     * SHA-256 over the canonical JSON of the root composite.
     *
     * Equal to `$this->root->checksum()`. Two plans with different
     * metadata but identical roots produce identical checksums — this
     * is intentional (see class docblock).
     */
    public function checksum(): string
    {
        return $this->root->checksum();
    }

    /**
     * Compiled-plan hash, if computed.
     *
     * Per §15 Q2, this is the SHA-256 over the canonical serialized
     * compiled DTO list (WP04 output: stable SQL or stable DTO JSON) —
     * NOT a hash of the source intent (that is {@see checksum()}).
     *
     * Returns `null` until the compiler has run. In WP03 every
     * `MigrationPlan` is constructed without the hash, so this returns
     * null. WP04's compile stage produces a new `MigrationPlan` (or a
     * derived DTO) carrying the populated `compiledDiffHash`.
     */
    public function diffHash(): ?string
    {
        return $this->compiledDiffHash;
    }
}
