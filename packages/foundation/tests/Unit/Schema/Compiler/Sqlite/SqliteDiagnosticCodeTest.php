<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Compiler\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteDiagnosticCode;

/**
 * Locks the public diagnostic-code strings. These cases participate in
 * operator runbooks and CI greps; once shipped, the string values MUST
 * NOT change without an ADR.
 */
#[CoversClass(SqliteDiagnosticCode::class)]
final class SqliteDiagnosticCodeTest extends TestCase
{
    #[Test]
    public function renameColumnUnsupportedCodeIsLocked(): void
    {
        self::assertSame(
            'RENAME_COLUMN_UNSUPPORTED_SQLITE_LT_3_25',
            SqliteDiagnosticCode::RenameColumnUnsupportedSqliteLt325->value,
        );
    }

    #[Test]
    public function alterColumnUnsupportedCodeIsLocked(): void
    {
        self::assertSame(
            'ALTER_COLUMN_UNSUPPORTED_SQLITE_V1',
            SqliteDiagnosticCode::AlterColumnUnsupportedSqliteV1->value,
        );
    }

    #[Test]
    public function foreignKeyUnsupportedCodeIsLocked(): void
    {
        self::assertSame(
            'FOREIGN_KEY_UNSUPPORTED_SQLITE_V1',
            SqliteDiagnosticCode::ForeignKeyUnsupportedSqliteV1->value,
        );
    }

    #[Test]
    public function operationNotImplementedCodeIsLocked(): void
    {
        // Legacy WP04 placeholder retained for compatibility per the
        // SqliteDiagnosticCode docblock — no current code path emits it.
        self::assertSame(
            'OPERATION_NOT_IMPLEMENTED',
            SqliteDiagnosticCode::OperationNotImplemented->value,
        );
    }

    #[Test]
    public function exposesAllDeclaredCases(): void
    {
        $values = array_map(
            static fn(SqliteDiagnosticCode $c): string => $c->value,
            SqliteDiagnosticCode::cases(),
        );

        // Locks both the count and the names so adding a case is an
        // intentional, reviewable change.
        self::assertSame(
            [
                'RENAME_COLUMN_UNSUPPORTED_SQLITE_LT_3_25',
                'ALTER_COLUMN_UNSUPPORTED_SQLITE_V1',
                'FOREIGN_KEY_UNSUPPORTED_SQLITE_V1',
                'OPERATION_NOT_IMPLEMENTED',
            ],
            $values,
        );
    }
}
