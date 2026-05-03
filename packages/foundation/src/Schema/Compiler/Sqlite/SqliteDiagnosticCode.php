<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Sqlite;

/**
 * Stable diagnostic codes emitted by the SQLite compiler.
 *
 * **Public contract.** Each case's string value is part of the operator-
 * facing diagnostic surface (see operator-diagnostics spec). Once a code
 * is shipped, the string MUST NOT change — downstream tools, runbooks,
 * and CI scripts may grep for it. Add new codes for new conditions; do
 * not repurpose existing ones.
 *
 * **Cases:**
 *
 * - `RENAME_COLUMN_UNSUPPORTED_SQLITE_LT_3_25` — runtime SQLite version
 *   is older than 3.25, which introduced `ALTER TABLE … RENAME COLUMN`.
 *   Operators on older builds must drop+add (with explicit data
 *   migration) instead.
 * - `ALTER_COLUMN_UNSUPPORTED_SQLITE_V1` — per §15 Q5, the v1 SQLite
 *   compiler refuses `AlterColumn`. SQLite cannot change a column's
 *   type or nullability in place; the only safe path is a table
 *   rebuild, which is destructive and intricate. Future ADRs may
 *   introduce a rebuild strategy; until then operators must split as
 *   drop+add with explicit data migration.
 * - `FOREIGN_KEY_UNSUPPORTED_SQLITE_V1` — per §15 Q6, the v1 SQLite
 *   compiler refuses `AddForeignKey` and `DropForeignKey`. SQLite
 *   cannot add or drop FK constraints on existing tables without a
 *   full rebuild; cross-dialect FK ops will land with the future
 *   MySQL / Postgres compilers.
 * - `OPERATION_NOT_IMPLEMENTED` — legacy WP04 placeholder retained for
 *   compatibility with any downstream tools that already grep for it.
 *   No code path in the post-WP05 compiler emits this; new conditions
 *   must use the specific validation codes above (or
 *   {@see \Waaseyaa\Foundation\Schema\Compiler\Validation\ValidationDiagnosticCode}
 *   for platform-neutral cases). Removal would be an API break — the
 *   case stays.
 *
 * Future WPs may append cases to this enum but MUST NOT remove or
 * rename existing ones without an ADR.
 */
enum SqliteDiagnosticCode: string
{
    case RenameColumnUnsupportedSqliteLt325 = 'RENAME_COLUMN_UNSUPPORTED_SQLITE_LT_3_25';
    case AlterColumnUnsupportedSqliteV1 = 'ALTER_COLUMN_UNSUPPORTED_SQLITE_V1';
    case ForeignKeyUnsupportedSqliteV1 = 'FOREIGN_KEY_UNSUPPORTED_SQLITE_V1';
    case OperationNotImplemented = 'OPERATION_NOT_IMPLEMENTED';
}
