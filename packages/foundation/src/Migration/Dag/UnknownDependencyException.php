<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Migration\Dag;

/**
 * Raised when a v2 migration declares a `dependencies()` entry that
 * matches no known node id and no known package in the current run.
 *
 * **Strict-mode scope.** Only v2 nodes throw this — legacy `$after`
 * entries that don't resolve are silently dropped to preserve the
 * pre-WP06 behavior of `Migrator::run()`. v2's `MigrationInterfaceV2`
 * contract documents that dependency strings are formal; misspellings
 * or missing packages should fail loud at compile/boot time.
 *
 * The diagnostic code `UNKNOWN_DEPENDENCY` is part of the operator
 * surface.
 */
final class UnknownDependencyException extends \RuntimeException
{
    public const DIAGNOSTIC_CODE = 'UNKNOWN_DEPENDENCY';

    public function __construct(
        public readonly string $dependency,
        public readonly string $sourceId,
    ) {
        parent::__construct(sprintf(
            'v2 migration "%s" declares dependency "%s", but no migration with that id and no package with that name is present in the current run. Check the spelling and ensure the dependency package is installed.',
            $sourceId,
            $dependency,
        ));
    }

    public function diagnosticCode(): string
    {
        return self::DIAGNOSTIC_CODE;
    }
}
