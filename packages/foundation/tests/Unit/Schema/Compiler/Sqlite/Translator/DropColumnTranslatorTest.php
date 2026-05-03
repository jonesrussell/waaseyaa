<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Compiler\Sqlite\Translator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\DropColumnTranslator;
use Waaseyaa\Foundation\Schema\Compiler\Validation\DestructiveOpBlockedException;
use Waaseyaa\Foundation\Schema\Compiler\Validation\PlanPolicy;
use Waaseyaa\Foundation\Schema\Compiler\Validation\ValidationDiagnosticCode;
use Waaseyaa\Foundation\Schema\Diff\DropColumn;

#[CoversClass(DropColumnTranslator::class)]
final class DropColumnTranslatorTest extends TestCase
{
    #[Test]
    public function defaultPolicyBlocksDropColumn(): void
    {
        try {
            DropColumnTranslator::translate(
                new DropColumn('users', 'email'),
                new PlanPolicy(),
            );
            self::fail('Expected DestructiveOpBlockedException under default policy.');
        } catch (DestructiveOpBlockedException $e) {
            self::assertSame(ValidationDiagnosticCode::DestructiveOpBlocked->value, $e->diagnosticCode);
            self::assertSame('drop_column', $e->opKind);
            self::assertStringContainsString('users', $e->getMessage());
            self::assertStringContainsString('email', $e->getMessage());
            self::assertStringContainsString('PlanPolicy(allowDestructive: true)', $e->getMessage());
        }
    }

    #[Test]
    public function destructiveOptInEmitsDropColumnSql(): void
    {
        $step = DropColumnTranslator::translate(
            new DropColumn('users', 'email'),
            new PlanPolicy(allowDestructive: true),
        );

        self::assertSame('execute_statement', $step->kind());
        self::assertSame(
            'ALTER TABLE "users" DROP COLUMN "email"',
            $step->sql(),
        );
    }

    #[Test]
    public function emittedSqlEscapesEmbeddedDoubleQuotes(): void
    {
        $step = DropColumnTranslator::translate(
            new DropColumn('weird"table', 'col"name'),
            new PlanPolicy(allowDestructive: true),
        );

        self::assertSame(
            'ALTER TABLE "weird""table" DROP COLUMN "col""name"',
            $step->sql(),
        );
    }
}
