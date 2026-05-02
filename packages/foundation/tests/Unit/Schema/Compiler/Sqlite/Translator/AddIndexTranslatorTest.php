<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Compiler\Sqlite\Translator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\AddIndexTranslator;
use Waaseyaa\Foundation\Schema\Diff\AddIndex;

#[CoversClass(AddIndexTranslator::class)]
final class AddIndexTranslatorTest extends TestCase
{
    #[Test]
    public function singleColumnNamedIndex(): void
    {
        $step = AddIndexTranslator::translate(
            new AddIndex('users', ['email'], 'idx_users_email'),
        );

        self::assertSame(
            'CREATE INDEX "idx_users_email" ON "users" ("email")',
            $step->sql(),
        );
        self::assertSame('idx_users_email', $step->name);
        self::assertSame(['email'], $step->columns);
        self::assertFalse($step->unique);
    }

    #[Test]
    public function uniqueCompositeIndexEmitsUniqueKeyword(): void
    {
        $step = AddIndexTranslator::translate(
            new AddIndex('users', ['email', 'tenant_id'], 'uq_users_email_tenant', unique: true),
        );

        self::assertSame(
            'CREATE UNIQUE INDEX "uq_users_email_tenant" ON "users" ("email", "tenant_id")',
            $step->sql(),
        );
        self::assertTrue($step->unique);
    }

    #[Test]
    public function anonymousIndexDerivesDeterministicName(): void
    {
        $step = AddIndexTranslator::translate(
            new AddIndex('users', ['email', 'tenant_id']),
        );

        self::assertSame('idx_users_email__tenant_id', $step->name);
        self::assertSame(
            'CREATE INDEX "idx_users_email__tenant_id" ON "users" ("email", "tenant_id")',
            $step->sql(),
        );
    }

    #[Test]
    public function derivedNameTruncatesAt63Characters(): void
    {
        $step = AddIndexTranslator::translate(
            new AddIndex('a_very_long_table_name', [
                'first_long_column',
                'second_long_column',
                'third_long_column',
            ]),
        );

        self::assertSame(63, strlen($step->name));
        self::assertStringStartsWith('idx_a_very_long_table_name_first_long_column__second_long', $step->name);
    }

    #[Test]
    public function emptyColumnsListIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AddIndexTranslator::translate(new AddIndex('users', [], 'idx_users_anything'));
    }
}
