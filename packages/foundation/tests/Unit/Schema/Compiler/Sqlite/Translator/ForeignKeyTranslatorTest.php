<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Compiler\Sqlite\Translator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteDiagnosticCode;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\ForeignKeyTranslator;
use Waaseyaa\Foundation\Schema\Compiler\Validation\ForeignKeyUnsupportedException;
use Waaseyaa\Foundation\Schema\Diff\AddForeignKey;
use Waaseyaa\Foundation\Schema\Diff\DropForeignKey;
use Waaseyaa\Foundation\Schema\Diff\ForeignKeySpec;

#[CoversClass(ForeignKeyTranslator::class)]
final class ForeignKeyTranslatorTest extends TestCase
{
    #[Test]
    public function rejectsAddForeignKeyWithStableSqliteV1Code(): void
    {
        $op = new AddForeignKey('orders', new ForeignKeySpec(
            referencedTable: 'users',
            localColumns: ['user_id'],
            referencedColumns: ['id'],
            onDelete: 'CASCADE',
            onUpdate: null,
            name: 'fk_orders_user',
        ));

        $thrown = null;
        try {
            ForeignKeyTranslator::translateAdd($op);
        } catch (ForeignKeyUnsupportedException $e) {
            $thrown = $e;
        }

        self::assertSame(
            SqliteDiagnosticCode::ForeignKeyUnsupportedSqliteV1->value,
            $thrown->diagnosticCode,
        );
        self::assertStringContainsString('orders', $thrown->getMessage());
        self::assertStringContainsString('fk_orders_user', $thrown->getMessage());
    }

    #[Test]
    public function rejectsDropForeignKeyWithStableSqliteV1Code(): void
    {
        $thrown = null;
        try {
            ForeignKeyTranslator::translateDrop(new DropForeignKey('orders', 'fk_orders_user_old'));
        } catch (ForeignKeyUnsupportedException $e) {
            $thrown = $e;
        }

        self::assertSame(
            SqliteDiagnosticCode::ForeignKeyUnsupportedSqliteV1->value,
            $thrown->diagnosticCode,
        );
        self::assertStringContainsString('orders', $thrown->getMessage());
        self::assertStringContainsString('fk_orders_user_old', $thrown->getMessage());
    }

    #[Test]
    public function addWithoutConstraintNameOmitsConstraintFragment(): void
    {
        $op = new AddForeignKey('orders', new ForeignKeySpec(
            referencedTable: 'users',
            localColumns: ['user_id'],
            referencedColumns: ['id'],
        ));

        $thrown = null;
        try {
            ForeignKeyTranslator::translateAdd($op);
        } catch (ForeignKeyUnsupportedException $e) {
            $thrown = $e;
        }

        self::assertStringNotContainsString('constraint ""', $thrown->getMessage());
    }
}
