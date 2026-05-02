<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Diff;

/**
 * Ordered list of atomic ops representing a structural change set.
 *
 * Per §15 Q3 (ratified 2026-05-02), `CompositeDiff([])` is the canonical
 * empty plan — there is no separate `Empty` type. The `MigrationPlan`
 * (lands in WP03) wraps metadata around a `CompositeDiff` root.
 *
 * **Identity contract:**
 *
 * - {@see toCanonical()} returns `['ops' => [each op's toCanonical()]]`
 *   with op order preserved verbatim.
 * - {@see toCanonicalJson()} encodes that via {@see CanonicalJson::encode()}.
 *   Two composites built from identical ops in the same order produce
 *   byte-identical JSON on every platform.
 * - {@see checksum()} is `sha256(toCanonicalJson())`. This is the single
 *   source of truth for plan identity — {@see equals()} compares by
 *   checksum.
 * - Reordering ops produces a different checksum **on purpose**. Order
 *   matters for application (e.g. `add_column` then `add_index` on that
 *   column has different semantics from the reverse).
 */
final readonly class CompositeDiff
{
    /**
     * @param list<SchemaDiffOp> $ops
     */
    public function __construct(public array $ops = []) {}

    /**
     * The canonical empty plan.
     *
     * Equivalent to `new CompositeDiff([])`. Provided as a named constructor
     * for readability at call sites that explicitly mean "no change".
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Canonical-JSON-ready representation.
     *
     * Shape: `['ops' => [op1.toCanonical(), op2.toCanonical(), ...]]`.
     *
     * @return array{ops: list<array<string, mixed>>}
     */
    public function toCanonical(): array
    {
        return [
            'ops' => array_map(static fn(SchemaDiffOp $op): array => $op->toCanonical(), $this->ops),
        ];
    }

    /**
     * Canonical JSON encoding of the plan.
     *
     * @throws \JsonException on encoding failure (see {@see CanonicalJson}).
     */
    public function toCanonicalJson(): string
    {
        return CanonicalJson::encode($this->toCanonical());
    }

    /**
     * SHA-256 checksum over the canonical JSON encoding.
     *
     * Stable across PHP versions and platforms. Use this as the plan's
     * identity in the migrations ledger.
     */
    public function checksum(): string
    {
        return hash('sha256', $this->toCanonicalJson());
    }

    /**
     * Plan equality by checksum.
     *
     * This is intentionally not a structural property-walker. Going
     * through the checksum keeps a single source of truth for identity:
     * if {@see toCanonical()} or {@see CanonicalJson} ever drift,
     * equality drifts with them — exactly once, in lockstep.
     */
    public function equals(self $other): bool
    {
        return $this->checksum() === $other->checksum();
    }
}
