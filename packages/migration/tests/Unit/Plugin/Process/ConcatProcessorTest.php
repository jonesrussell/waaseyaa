<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit\Plugin\Process;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Migration\Plugin\Process\ConcatProcessor;
use Waaseyaa\Migration\Plugin\ProcessContext;
use Waaseyaa\Migration\Plugin\ReservedPluginIds;
use Waaseyaa\Migration\Plugin\SourceRecord;
use Waaseyaa\Migration\Plugin\WriteResult;
use Waaseyaa\Migration\SourceId;

#[CoversClass(ConcatProcessor::class)]
final class ConcatProcessorTest extends TestCase
{
    #[Test]
    public function id_is_concat(): void
    {
        $p = new ConcatProcessor([]);
        self::assertSame(ReservedPluginIds::CONCAT, $p->id());
        self::assertSame('stable', $p->stability());
    }

    #[Test]
    public function concatenates_literals_and_field_refs(): void
    {
        $p = new ConcatProcessor(parts: ['@first_name', ' ', '@last_name']);
        $ctx = $this->context(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

        self::assertSame('Ada Lovelace', $p->transform(null, $ctx));
    }

    #[Test]
    public function honours_separator(): void
    {
        $p = new ConcatProcessor(parts: ['@a', '@b', '@c'], separator: '/');
        $ctx = $this->context(['a' => '1', 'b' => '2', 'c' => '3']);

        self::assertSame('1/2/3', $p->transform(null, $ctx));
    }

    #[Test]
    public function null_source_fields_become_empty_string_not_literal_null(): void
    {
        $p = new ConcatProcessor(parts: ['@missing', '-', '@present']);
        $ctx = $this->context(['present' => 'x']);

        // Result is '' + '-' + 'x' = '-x' (joined by empty default separator)
        self::assertSame('-x', $p->transform(null, $ctx));
    }

    #[Test]
    public function empty_parts_yield_empty_string(): void
    {
        $p = new ConcatProcessor(parts: []);
        $ctx = $this->context([]);

        self::assertSame('', $p->transform(null, $ctx));
    }

    #[Test]
    public function bare_at_prefix_is_treated_as_empty_field_ref(): void
    {
        $p = new ConcatProcessor(parts: ['prefix-', '@', '-suffix']);
        $ctx = $this->context([]);

        self::assertSame('prefix--suffix', $p->transform(null, $ctx));
    }

    #[Test]
    public function literal_strings_pass_through(): void
    {
        $p = new ConcatProcessor(parts: ['hello', ', ', 'world']);
        $ctx = $this->context([]);

        self::assertSame('hello, world', $p->transform(null, $ctx));
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function context(array $fields): ProcessContext
    {
        return new ProcessContext(
            sourceRecord: new SourceRecord('csv', $fields),
            migrationId: 'm1',
            destinationField: 'composite',
            lookup: static fn (string $m, SourceId $id): ?WriteResult => null,
        );
    }
}
