<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Compiler\Sqlite\Translator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\SqliteColumnType;
use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;

#[CoversClass(SqliteColumnType::class)]
final class SqliteColumnTypeTest extends TestCase
{
    #[Test]
    public function intMapsToInteger(): void
    {
        self::assertSame(
            'INTEGER',
            SqliteColumnType::render(new ColumnSpec(type: 'int', nullable: true)),
        );
    }

    #[Test]
    public function booleanMapsToInteger(): void
    {
        self::assertSame(
            'INTEGER',
            SqliteColumnType::render(new ColumnSpec(type: 'boolean', nullable: true)),
        );
    }

    #[Test]
    public function textMapsToText(): void
    {
        self::assertSame(
            'TEXT',
            SqliteColumnType::render(new ColumnSpec(type: 'text', nullable: true)),
        );
    }

    #[Test]
    public function floatMapsToReal(): void
    {
        self::assertSame(
            'REAL',
            SqliteColumnType::render(new ColumnSpec(type: 'float', nullable: true)),
        );
    }

    #[Test]
    public function varcharRequiresLength(): void
    {
        self::assertSame(
            'VARCHAR(255)',
            SqliteColumnType::render(new ColumnSpec(type: 'varchar', nullable: false, length: 255)),
        );
    }

    #[Test]
    public function varcharWithoutLengthIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SqliteColumnType::render(new ColumnSpec(type: 'varchar', nullable: false));
    }

    #[Test]
    public function varcharWithZeroLengthIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SqliteColumnType::render(new ColumnSpec(type: 'varchar', nullable: false, length: 0));
    }

    #[Test]
    public function unknownTokenIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SqliteColumnType::render(new ColumnSpec(type: 'json', nullable: true));
    }
}
