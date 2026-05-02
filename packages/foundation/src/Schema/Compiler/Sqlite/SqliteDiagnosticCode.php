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
 * **Cases (WP04 — additive ops):**
 *
 * - `RENAME_COLUMN_UNSUPPORTED_SQLITE_LT_3_25` — runtime SQLite version
 *   is older than 3.25, which introduced `ALTER TABLE … RENAME COLUMN`.
 *   Operators on older builds must drop+add (with explicit data
 *   migration) instead. Per §15 Q5 / Q6 family of capability gates.
 * - `OPERATION_NOT_IMPLEMENTED` — provisional code emitted when WP04
 *   encounters an op kind whose translator does not yet exist (e.g.
 *   `AlterColumn`, `DropColumn`, `DropIndex`, `AddForeignKey`,
 *   `DropForeignKey`). WP05 replaces these with proper validation-gate
 *   codes (`ALTER_COLUMN_UNSUPPORTED_SQLITE_V1`,
 *   `FOREIGN_KEY_UNSUPPORTED_SQLITE_V1`, etc.). Until WP05 lands,
 *   callers see this single placeholder code and the op kind in the
 *   exception message.
 *
 * Future WPs (WP05 in particular) will append cases to this enum but
 * MUST NOT remove or rename existing ones without an ADR.
 */
enum SqliteDiagnosticCode: string
{
    case RenameColumnUnsupportedSqliteLt325 = 'RENAME_COLUMN_UNSUPPORTED_SQLITE_LT_3_25';
    case OperationNotImplemented = 'OPERATION_NOT_IMPLEMENTED';
}
