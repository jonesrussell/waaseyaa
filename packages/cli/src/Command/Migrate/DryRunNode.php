<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command\Migrate;

/**
 * One node entry in a {@see DryRunResult}.
 *
 * `steps` is empty for:
 * - Legacy nodes (their `up()` body is imperative — we cannot pre-compile).
 * - Already-applied nodes (would be a no-op at apply time, no SQL to preview).
 *
 * For v2 pending nodes, `steps` carries the canonical-JSON dictionary of
 * each {@see \Waaseyaa\Foundation\Schema\Compiler\CompiledStep} the
 * compiler would emit.
 */
final readonly class DryRunNode
{
    /**
     * @param list<string>                    $dependencies
     * @param list<array<string, mixed>>      $steps
     */
    public function __construct(
        public string $id,
        public string $package,
        public string $kind,
        public array $dependencies,
        public array $steps,
        public bool $alreadyApplied,
    ) {}
}
