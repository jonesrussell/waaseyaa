<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler;

use Waaseyaa\Foundation\Schema\Diff\CanonicalJson;

/**
 * Immutable, ordered list of {@see CompiledStep} produced by a platform
 * compiler.
 *
 * **Identity contract (parallels {@see \Waaseyaa\Foundation\Schema\Diff\CompositeDiff}):**
 *
 * - {@see toCanonical()} returns `['steps' => [each step's toCanonical()]]`
 *   with step order preserved verbatim.
 * - {@see toCanonicalJson()} encodes that via {@see CanonicalJson::encode()}.
 *   Two plans built from identical steps in the same order produce
 *   byte-identical JSON on every PHP version on every platform.
 * - {@see diffHash()} is `sha256(toCanonicalJson())`. This is the value
 *   recorded in `migration_repository.diff_hash` (see WP09) — the single
 *   source of truth for compiled-plan identity. Verify mode (WP10)
 *   recomputes a plan and compares its `diffHash()` to the ledger row.
 *
 * Per the design's §15 Q2 ratification, `diff_hash` is intentionally a
 * separate hash from the source `CompositeDiff::checksum()`: the source
 * tracks intent (what the operator authored), the compiled hash tracks
 * the actual SQL plan (what the database will see). They differ because
 * the same `CompositeDiff` compiled with different platforms / different
 * compiler versions produces different SQL.
 */
final readonly class CompiledMigrationPlan
{
    /**
     * @param list<CompiledStep> $steps
     */
    public function __construct(public array $steps = []) {}

    public function isEmpty(): bool
    {
        return $this->steps === [];
    }

    /**
     * Canonical-JSON-ready representation.
     *
     * Shape: `['steps' => [step1.toCanonical(), step2.toCanonical(), ...]]`.
     *
     * @return array{steps: list<array<string, mixed>>}
     */
    public function toCanonical(): array
    {
        return [
            'steps' => array_map(static fn(CompiledStep $step): array => $step->toCanonical(), $this->steps),
        ];
    }

    /**
     * Canonical JSON encoding of the compiled plan.
     *
     * @throws \JsonException on encoding failure (see {@see CanonicalJson}).
     */
    public function toCanonicalJson(): string
    {
        return CanonicalJson::encode($this->toCanonical());
    }

    /**
     * SHA-256 over the canonical JSON encoding — the value WP09 stores
     * in the migrations ledger as `diff_hash`.
     */
    public function diffHash(): string
    {
        return hash('sha256', $this->toCanonicalJson());
    }
}
