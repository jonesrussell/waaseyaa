<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Compiler\Sqlite\Translator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\RenameTableTranslator;
use Waaseyaa\Foundation\Schema\Diff\RenameTable;

#[CoversClass(RenameTableTranslator::class)]
final class RenameTableTranslatorTest extends TestCase
{
    #[Test]
    public function emitsRenameToSql(): void
    {
        $step = RenameTableTranslator::translate(new RenameTable('widgets', 'gizmos'));

        self::assertSame('execute_statement', $step->kind());
        self::assertSame(
            'ALTER TABLE "widgets" RENAME TO "gizmos"',
            $step->sql(),
        );
    }

    #[Test]
    public function escapesEmbeddedDoubleQuotes(): void
    {
        $step = RenameTableTranslator::translate(new RenameTable('a"b', 'c"d'));

        self::assertSame(
            'ALTER TABLE "a""b" RENAME TO "c""d"',
            $step->sql(),
        );
    }
}
