<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Validation;

/**
 * Platform-neutral diagnostic codes emitted by the validation layer.
 *
 * **Public contract.** Each case's string value is part of the operator-
 * facing diagnostic surface (operator runbooks, dashboards, CI greps).
 * Once shipped the strings MUST NOT change. Add new codes for new
 * conditions; do not repurpose existing ones.
 *
 * Sibling enum {@see \Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteDiagnosticCode}
 * holds SQLite-specific codes (`ALTER_COLUMN_UNSUPPORTED_SQLITE_V1`,
 * `FOREIGN_KEY_UNSUPPORTED_SQLITE_V1`, `RENAME_COLUMN_UNSUPPORTED_SQLITE_LT_3_25`,
 * etc.). Future MySQL / Postgres compilers will add their own platform
 * enums; the codes in *this* enum are the ones any platform shares.
 *
 * **Cases:**
 *
 * - `UNKNOWN_OP_KIND` — compiler encountered a {@see \Waaseyaa\Foundation\Schema\Diff\SchemaDiffOp}
 *   whose kind is not in the platform compiler's dispatch table. Indicates
 *   either a new op kind shipped without compiler coverage, or a custom
 *   op type smuggled into a {@see \Waaseyaa\Foundation\Schema\Diff\CompositeDiff}.
 * - `DESTRUCTIVE_OP_BLOCKED` — a destructive op (`DropColumn`,
 *   `DropIndex`, future `DropTable`) appeared in the diff but the
 *   {@see PlanPolicy} did not opt-in via `allowDestructive: true`. The
 *   gate is platform-neutral: the same policy applies regardless of
 *   target dialect.
 * - `ILLEGAL_OP_ORDER` — same-composite ordering violation detected by
 *   {@see OrderingValidator} (e.g. `AddIndex` referencing a column added
 *   later in the same composite, or `AddColumn` colliding with a
 *   prior `RenameColumn` target name).
 */
enum ValidationDiagnosticCode: string
{
    case UnknownOpKind = 'UNKNOWN_OP_KIND';
    case DestructiveOpBlocked = 'DESTRUCTIVE_OP_BLOCKED';
    case IllegalOpOrder = 'ILLEGAL_OP_ORDER';
}
