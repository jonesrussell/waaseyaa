<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Migration\Exception\ProcessException;

#[CoversClass(ProcessException::class)]
final class ProcessExceptionTest extends TestCase
{
    #[Test]
    public function round_trips_all_readonly_properties(): void
    {
        $previous = new \RuntimeException('underlying');

        $e = new ProcessException(
            processCode: ProcessException::CODE_LOOKUP_MISS,
            sourceField: 'post_author',
            migrationId: 'wp_posts',
            message: 'no row',
            previous: $previous,
        );

        self::assertSame(ProcessException::CODE_LOOKUP_MISS, $e->processCode);
        self::assertSame('post_author', $e->sourceField);
        self::assertSame('wp_posts', $e->migrationId);
        self::assertSame('no row', $e->getMessage());
        self::assertSame($previous, $e->getPrevious());
        self::assertSame(0, $e->getCode(), 'Integer Exception::code stays at 0; semantics live in $code.');
    }

    #[Test]
    public function rejects_empty_process_code(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ProcessException(processCode: '', sourceField: 'f', migrationId: 'm', message: 'msg');
    }

    #[Test]
    public function rejects_empty_source_field(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ProcessException(processCode: 'X', sourceField: '', migrationId: 'm', message: 'msg');
    }

    #[Test]
    public function rejects_empty_migration_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ProcessException(processCode: 'X', sourceField: 'f', migrationId: '', message: 'msg');
    }

    #[Test]
    public function codes_table_enumerates_shipped_codes(): void
    {
        self::assertContains(ProcessException::CODE_LOOKUP_MISS, ProcessException::CODES);
        self::assertContains(ProcessException::CODE_TYPE_COERCE_FAIL, ProcessException::CODES);
        // Append-only invariant: codes are non-empty.
        foreach (ProcessException::CODES as $code) {
            self::assertNotSame('', $code);
        }
    }
}
