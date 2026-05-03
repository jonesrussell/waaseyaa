<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Discovery;

/**
 * Raised when a package's `extra.waaseyaa.migrations` entry is neither
 * a string nor a list of strings (per spec §15 Q9 / WP11).
 *
 * The accepted shapes are:
 *
 * - `"migrations": "migrations"` — single legacy directory path.
 * - `"migrations": ["Vendor\\Pkg\\Migrations", "../patches/v2"]` —
 *   ordered list of FQCN namespace roots and/or path strings.
 *
 * Anything else (objects, nested arrays, non-string scalars) fails
 * loud at compile time with code `INVALID_MIGRATION_ENTRY`. Silent
 * skip would let typos pass through and never apply migrations the
 * operator believed were declared.
 */
final class InvalidMigrationEntryException extends \RuntimeException
{
    public const DIAGNOSTIC_CODE = 'INVALID_MIGRATION_ENTRY';

    public function __construct(
        public readonly string $packageName,
        string $detail,
    ) {
        parent::__construct(sprintf(
            'Package "%s" has an invalid `extra.waaseyaa.migrations` entry: %s. Accepted shapes are a single path string or an ordered list of namespace FQCN / path strings.',
            $packageName,
            $detail,
        ));
    }

    public function diagnosticCode(): string
    {
        return self::DIAGNOSTIC_CODE;
    }
}
