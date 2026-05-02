<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator;

/**
 * SQLite identifier and literal quoting helpers.
 *
 * Centralised so every translator quotes identifiers identically and the
 * golden hashes in the test suite stay stable. SQLite accepts both
 * backticks and double-quotes for identifiers; we use double-quotes
 * (SQL-standard) to keep the SQL portable to future MySQL / Postgres
 * compilers.
 */
final class SqliteIdentifier
{
    /**
     * Quote a SQL identifier (table, column, index name) with double
     * quotes, escaping any embedded double-quote per SQL convention.
     */
    public static function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * Render a default-clause literal for a SQLite column DEFAULT.
     *
     * Supported types: `string`, `int`, `float`, `bool`, `null`. Arrays
     * and objects are rejected — DEFAULT must be a literal, not a
     * structured value.
     */
    public static function literal(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value) => (string) $value,
            is_float($value) => self::renderFloat($value),
            is_string($value) => "'" . str_replace("'", "''", $value) . "'",
            default => throw new \InvalidArgumentException(
                'Unsupported DEFAULT literal type: ' . get_debug_type($value),
            ),
        };
    }

    /**
     * Shortest round-trip float rendering — never `1.0` for integer
     * values that arrived as float, never `JSON_PRESERVE_ZERO_FRACTION`
     * weirdness. Matches CanonicalJson's float discipline.
     */
    private static function renderFloat(float $value): string
    {
        if (is_nan($value) || is_infinite($value)) {
            throw new \InvalidArgumentException('NaN / Inf cannot be rendered as a SQL DEFAULT.');
        }

        return (string) $value;
    }
}
