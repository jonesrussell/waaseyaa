<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Compiler\Sqlite\Translator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\AddColumnTranslator;
use Waaseyaa\Foundation\Schema\Diff\AddColumn;
use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;

#[CoversClass(AddColumnTranslator::class)]
final class AddColumnTranslatorTest extends TestCase
{
    #[Test]
    public function nullableIntColumnWithoutDefault(): void
    {
        $step = AddColumnTranslator::translate(
            new AddColumn('widgets', 'archived_at', new ColumnSpec(type: 'int', nullable: true)),
        );

        self::assertSame(
            'ALTER TABLE "widgets" ADD COLUMN "archived_at" INTEGER',
            $step->sql(),
        );
        self::assertSame('widgets', $step->table);
        self::assertSame('archived_at', $step->column);
    }

    #[Test]
    public function notNullVarcharWithStringDefault(): void
    {
        $step = AddColumnTranslator::translate(
            new AddColumn('widgets', 'name', new ColumnSpec(
                type: 'varchar',
                nullable: false,
                default: 'unknown',
                length: 64,
            )),
        );

        self::assertSame(
            "ALTER TABLE \"widgets\" ADD COLUMN \"name\" VARCHAR(64) NOT NULL DEFAULT 'unknown'",
            $step->sql(),
        );
    }

    #[Test]
    public function booleanColumnUsesIntegerAffinityAndZeroOneDefault(): void
    {
        $step = AddColumnTranslator::translate(
            new AddColumn('widgets', 'is_active', new ColumnSpec(
                type: 'boolean',
                nullable: false,
                default: true,
            )),
        );

        self::assertSame(
            'ALTER TABLE "widgets" ADD COLUMN "is_active" INTEGER NOT NULL DEFAULT 1',
            $step->sql(),
        );
    }

    #[Test]
    public function escapesSingleQuotesInStringDefault(): void
    {
        $step = AddColumnTranslator::translate(
            new AddColumn('widgets', 'note', new ColumnSpec(
                type: 'text',
                nullable: false,
                default: "it's fine",
            )),
        );

        self::assertSame(
            "ALTER TABLE \"widgets\" ADD COLUMN \"note\" TEXT NOT NULL DEFAULT 'it''s fine'",
            $step->sql(),
        );
    }

    #[Test]
    public function escapesDoubleQuotesInIdentifiers(): void
    {
        $step = AddColumnTranslator::translate(
            new AddColumn('weird"table', 'col"name', new ColumnSpec(type: 'int', nullable: true)),
        );

        self::assertSame(
            'ALTER TABLE "weird""table" ADD COLUMN "col""name" INTEGER',
            $step->sql(),
        );
    }
}
