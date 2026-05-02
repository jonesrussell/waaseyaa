<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator;

use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;

/**
 * Maps {@see ColumnSpec::$type} tokens to SQLite column-type SQL.
 *
 * Per spec §5.4, the semantic mapping must align with
 * `SqlSchemaHandler::deriveColumnSpec()` in `waaseyaa/entity-storage`
 * (Layer 1). The compiler implements its own lookup here — foundation
 * (Layer 0) cannot import from entity-storage. The two tables stay
 * aligned by convention, locked by integration tests in WP08.
 *
 * **Supported tokens (matches {@see ColumnSpec} docblock):**
 *
 * | Token     | SQLite SQL                  | Notes                  |
 * |-----------|-----------------------------|------------------------|
 * | `varchar` | `VARCHAR(<length>)`         | length required        |
 * | `text`    | `TEXT`                      | length ignored         |
 * | `int`     | `INTEGER`                   | length ignored         |
 * | `boolean` | `INTEGER`                   | 0/1 storage            |
 * | `float`   | `REAL`                      | length ignored         |
 *
 * Unknown tokens throw — silently mapping to `TEXT` would produce a
 * working DDL with a wrong storage class and break verify mode later.
 */
final class SqliteColumnType
{
    public static function render(ColumnSpec $spec): string
    {
        return match ($spec->type) {
            'varchar' => self::varchar($spec),
            'text' => 'TEXT',
            'int' => 'INTEGER',
            'boolean' => 'INTEGER',
            'float' => 'REAL',
            default => throw new \InvalidArgumentException(
                'Unknown ColumnSpec type token: ' . $spec->type,
            ),
        };
    }

    private static function varchar(ColumnSpec $spec): string
    {
        if ($spec->length === null || $spec->length <= 0) {
            throw new \InvalidArgumentException(
                'varchar ColumnSpec requires a positive length.',
            );
        }

        return 'VARCHAR(' . $spec->length . ')';
    }
}
