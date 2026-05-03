<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Compiler\Sqlite\Translator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteDiagnosticCode;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\AlterColumnTranslator;
use Waaseyaa\Foundation\Schema\Compiler\Validation\AlterColumnUnsupportedException;
use Waaseyaa\Foundation\Schema\Diff\AlterColumn;
use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;

#[CoversClass(AlterColumnTranslator::class)]
final class AlterColumnTranslatorTest extends TestCase
{
    #[Test]
    public function rejectsAlterColumnWithStableSqliteV1Code(): void
    {
        $thrown = null;
        try {
            AlterColumnTranslator::translate(
                new AlterColumn('users', 'email', new ColumnSpec(type: 'text', nullable: true)),
            );
        } catch (AlterColumnUnsupportedException $e) {
            $thrown = $e;
        }

        self::assertSame(
            SqliteDiagnosticCode::AlterColumnUnsupportedSqliteV1->value,
            $thrown->diagnosticCode,
        );
        self::assertStringContainsString('users', $thrown->getMessage());
        self::assertStringContainsString('email', $thrown->getMessage());
        self::assertStringContainsString('SQLite', $thrown->getMessage());
    }

    #[Test]
    public function exceptionMessageDoesNotLeakFilesystemPaths(): void
    {
        $thrown = null;
        try {
            AlterColumnTranslator::translate(
                new AlterColumn('users', 'email', new ColumnSpec(type: 'text', nullable: true)),
            );
        } catch (AlterColumnUnsupportedException $e) {
            $thrown = $e;
        }

        self::assertDoesNotMatchRegularExpression('#(/var/|/home/|/tmp/|\\\\)#', $thrown->getMessage());
    }
}
