<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Backend;

/**
 * @api
 *
 * Normative §8.2 type-mapping table for the sql-column backend.
 *
 * Maps FieldDefinition type strings to SQL column type strings for a given
 * database platform. The platform identifiers returned by
 * {@see self::platformKey()} are the canonical keys used throughout this
 * class; call sites obtain them via that method rather than hard-coding strings.
 *
 * Decimal lossless round-trip:
 * - SQLite: stored as TEXT (string representation, no IEEE-754 loss)
 * - Postgres: stored as NUMERIC(p, s) with caller-supplied precision/scale
 *
 * float_vector_<n> is rejected at this layer with an explicit exception;
 * callers MUST route vector fields via `FieldDefinition::storedIn('vector')`.
 */
final class TypeMapping
{
    public const PLATFORM_SQLITE = 'sqlite';
    public const PLATFORM_POSTGRESQL = 'postgresql';

    /**
     * Derive the SQL column type string for the given field type and platform.
     *
     * @param string      $platform   Platform key — use {@see self::platformKey()} to obtain.
     * @param string      $fieldType  FieldDefinition::getType() value (case-insensitive).
     * @param int|null    $length     Optional VARCHAR length (used for `string` on Postgres).
     * @param int|null    $precision  Optional NUMERIC precision (used for `decimal` on Postgres).
     * @param int|null    $scale      Optional NUMERIC scale (used for `decimal` on Postgres).
     *
     * @throws \InvalidArgumentException For float_vector_<n> types, which must be routed to the
     *                                   vector backend via FieldDefinition::storedIn('vector').
     * @throws \UnexpectedValueException For unknown field types (fallback: TEXT with a warning).
     *
     * @api
     */
    public static function columnTypeFor(
        string $platform,
        string $fieldType,
        ?int $length = null,
        ?int $precision = null,
        ?int $scale = null,
    ): string {
        $type = strtolower($fieldType);

        // float_vector_<n> is forbidden at this layer (§8.2).
        if (preg_match('/^float_vector_\d+$/', $type)) {
            throw new \InvalidArgumentException(sprintf(
                'Field type "%s" cannot be stored by the sql-column backend. '
                . 'Route this field to the vector backend via FieldDefinition::storedIn(\'vector\').',
                $fieldType,
            ));
        }

        return match ($platform) {
            self::PLATFORM_SQLITE => self::sqliteType($type, $precision, $scale),
            self::PLATFORM_POSTGRESQL => self::postgresType($type, $length, $precision, $scale),
            default => self::sqliteType($type, $precision, $scale), // safe fallback
        };
    }

    /**
     * Derive the platform key from a Doctrine DBAL platform class name.
     *
     * Doctrine platform classes are in the form `Doctrine\DBAL\Platforms\*Platform`.
     * We extract the short name and normalise to our canonical keys.
     *
     * @api
     */
    public static function platformKey(\Doctrine\DBAL\Platforms\AbstractPlatform $platform): string
    {
        $class = get_class($platform);
        $short = strtolower(basename(str_replace('\\', '/', $class)));

        if (str_contains($short, 'sqlite')) {
            return self::PLATFORM_SQLITE;
        }

        if (str_contains($short, 'postgresql') || str_contains($short, 'pgsql') || str_contains($short, 'postgres')) {
            return self::PLATFORM_POSTGRESQL;
        }

        // Unknown platform: fall back to SQLite-compatible types (broadest support).
        return self::PLATFORM_SQLITE;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * SQLite column types per §8.2.
     *
     * SQLite type affinity is text-based, so we emit the canonical affinity
     * strings rather than dialect-specific keywords where possible.
     *
     * @return string SQL column type string for use in CREATE TABLE DDL.
     */
    private static function sqliteType(string $type, ?int $precision, ?int $scale): string
    {
        return match ($type) {
            'string'   => 'TEXT',
            'text'     => 'TEXT',
            'int'      => 'INTEGER',
            'integer'  => 'INTEGER',
            'bigint'   => 'INTEGER',
            'bool'     => 'INTEGER',       // 0/1 per §8.2
            'boolean'  => 'INTEGER',
            'datetime' => 'TEXT',          // ISO 8601 per §8.2
            'json'     => 'TEXT',
            'uuid'     => 'TEXT',
            'float'    => 'REAL',
            'decimal'  => 'TEXT',          // lossless string per §8.2
            'numeric'  => 'TEXT',
            default    => 'TEXT',          // safe fallback
        };
    }

    /**
     * Postgres column types per §8.2.
     *
     * @return string SQL column type string for use in CREATE TABLE DDL.
     */
    private static function postgresType(
        string $type,
        ?int $length,
        ?int $precision,
        ?int $scale,
    ): string {
        return match ($type) {
            'string'   => ($length !== null && $length > 0) ? 'VARCHAR(' . $length . ')' : 'TEXT',
            'text'     => 'TEXT',
            'int'      => 'INTEGER',
            'integer'  => 'INTEGER',
            'bigint'   => 'BIGINT',
            'bool'     => 'BOOLEAN',
            'boolean'  => 'BOOLEAN',
            'datetime' => 'TIMESTAMPTZ',
            'json'     => 'JSONB',
            'uuid'     => 'UUID',
            'float'    => 'DOUBLE PRECISION',
            'decimal'  => self::numericType($precision, $scale),
            'numeric'  => self::numericType($precision, $scale),
            default    => 'TEXT',          // safe fallback
        };
    }

    /**
     * Build NUMERIC(p,s) or NUMERIC if precision/scale are absent.
     */
    private static function numericType(?int $precision, ?int $scale): string
    {
        if ($precision !== null && $scale !== null) {
            return 'NUMERIC(' . $precision . ',' . $scale . ')';
        }

        if ($precision !== null) {
            return 'NUMERIC(' . $precision . ')';
        }

        return 'NUMERIC';
    }
}
