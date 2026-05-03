<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Compiler\Sqlite\Translator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\DropIndexTranslator;
use Waaseyaa\Foundation\Schema\Compiler\Validation\DestructiveOpBlockedException;
use Waaseyaa\Foundation\Schema\Compiler\Validation\PlanPolicy;
use Waaseyaa\Foundation\Schema\Compiler\Validation\ValidationDiagnosticCode;
use Waaseyaa\Foundation\Schema\Diff\DropIndex;

#[CoversClass(DropIndexTranslator::class)]
final class DropIndexTranslatorTest extends TestCase
{
    #[Test]
    public function defaultPolicyBlocksDropIndex(): void
    {
        try {
            DropIndexTranslator::translate(
                new DropIndex('users', 'idx_users_email'),
                new PlanPolicy(),
            );
            self::fail('Expected DestructiveOpBlockedException under default policy.');
        } catch (DestructiveOpBlockedException $e) {
            self::assertSame(ValidationDiagnosticCode::DestructiveOpBlocked->value, $e->diagnosticCode);
            self::assertSame('drop_index', $e->opKind);
            self::assertStringContainsString('idx_users_email', $e->getMessage());
        }
    }

    #[Test]
    public function destructiveOptInEmitsDropIndexSql(): void
    {
        $step = DropIndexTranslator::translate(
            new DropIndex('users', 'idx_users_email'),
            new PlanPolicy(allowDestructive: true),
        );

        self::assertSame('DROP INDEX "idx_users_email"', $step->sql());
    }

    #[Test]
    public function anonymousByColumnsRejectedInV1(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DropIndexTranslator::translate(
            new DropIndex('users', null, ['email']),
            new PlanPolicy(allowDestructive: true),
        );
    }
}
