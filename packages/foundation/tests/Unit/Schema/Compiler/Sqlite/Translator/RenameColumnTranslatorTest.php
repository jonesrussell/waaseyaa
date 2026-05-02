<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Compiler\Sqlite\Translator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCapabilities;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCompilerException;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteDiagnosticCode;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\RenameColumnTranslator;
use Waaseyaa\Foundation\Schema\Diff\RenameColumn;

#[CoversClass(RenameColumnTranslator::class)]
final class RenameColumnTranslatorTest extends TestCase
{
    #[Test]
    public function emitsRenameColumnSqlOnSupportedRuntime(): void
    {
        $step = RenameColumnTranslator::translate(
            new RenameColumn('widgets', 'name', 'title'),
            SqliteCapabilities::forVersion('3.40.0'),
        );

        self::assertSame('execute_statement', $step->kind());
        self::assertSame(
            'ALTER TABLE "widgets" RENAME COLUMN "name" TO "title"',
            $step->sql(),
        );
    }

    #[Test]
    public function rejectsOnUnsupportedRuntimeWithStableCode(): void
    {
        try {
            RenameColumnTranslator::translate(
                new RenameColumn('widgets', 'name', 'title'),
                SqliteCapabilities::forVersion('3.20.0'),
            );
            self::fail('Expected SqliteCompilerException for unsupported runtime.');
        } catch (SqliteCompilerException $e) {
            self::assertSame(
                SqliteDiagnosticCode::RenameColumnUnsupportedSqliteLt325,
                $e->diagnosticCode(),
            );
            self::assertStringContainsString('3.20.0', $e->getMessage());
            self::assertStringContainsString('widgets', $e->getMessage());
            self::assertStringContainsString('name', $e->getMessage());
            self::assertStringContainsString('title', $e->getMessage());
        }
    }

    #[Test]
    public function exceptionMessageDoesNotLeakFilesystemPaths(): void
    {
        try {
            RenameColumnTranslator::translate(
                new RenameColumn('widgets', 'name', 'title'),
                SqliteCapabilities::forVersion('3.20.0'),
            );
            self::fail('Expected SqliteCompilerException.');
        } catch (SqliteCompilerException $e) {
            // Compiler is filesystem-pure (spec §7.3); no path-like fragment
            // should appear in operator-facing diagnostics.
            self::assertDoesNotMatchRegularExpression('#(/var/|/home/|/tmp/|\\\\)#', $e->getMessage());
        }
    }
}
