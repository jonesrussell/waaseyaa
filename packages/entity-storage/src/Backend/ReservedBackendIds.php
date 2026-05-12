<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Backend;

/**
 * @api
 *
 * Reserved backend-id constants — stable surface, charter §5.3.
 *
 * These ids are owned by the framework. Third-party providers MUST NOT
 * register backends under any of these ids; {@see BackendRegistrar} will
 * raise {@see \Waaseyaa\EntityStorage\Exception\BackendIdCollisionException}
 * at boot if they do.
 */
final class ReservedBackendIds
{
    /**
     * The default blob-based SQL backend.
     * Stores all field values as JSON in the `_data` column.
     */
    public const SQL_BLOB = 'sql-blob';

    /**
     * The column-based SQL backend.
     * Materialises each field as a dedicated SQL column with a typed mapping.
     */
    public const SQL_COLUMN = 'sql-column';

    /**
     * The vector-embedding backend.
     * Reserved for future use; not yet implemented.
     */
    public const VECTOR = 'vector';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::SQL_BLOB, self::SQL_COLUMN, self::VECTOR];
    }
}
