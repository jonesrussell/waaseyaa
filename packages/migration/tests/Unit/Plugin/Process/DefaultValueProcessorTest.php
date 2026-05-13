<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit\Plugin\Process;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Migration\Plugin\Process\DefaultValueProcessor;
use Waaseyaa\Migration\Plugin\ProcessContext;
use Waaseyaa\Migration\Plugin\ReservedPluginIds;
use Waaseyaa\Migration\Plugin\SourceRecord;
use Waaseyaa\Migration\Plugin\WriteResult;
use Waaseyaa\Migration\SourceId;

#[CoversClass(DefaultValueProcessor::class)]
final class DefaultValueProcessorTest extends TestCase
{
    #[Test]
    public function id_is_default_value(): void
    {
        $p = new DefaultValueProcessor(default: 'fallback');
        self::assertSame(ReservedPluginIds::DEFAULT_VALUE, $p->id());
        self::assertSame('stable', $p->stability());
    }

    #[Test]
    public function null_is_replaced_by_default(): void
    {
        $p = new DefaultValueProcessor(default: 'fallback');
        self::assertSame('fallback', $p->transform(null, $this->context()));
    }

    #[Test]
    public function empty_string_is_replaced_when_flag_on(): void
    {
        $p = new DefaultValueProcessor(default: 'fallback');
        self::assertSame('fallback', $p->transform('', $this->context()));
    }

    #[Test]
    public function empty_string_is_preserved_when_flag_off(): void
    {
        $p = new DefaultValueProcessor(default: 'fallback', treatEmptyStringAsNull: false);
        self::assertSame('', $p->transform('', $this->context()));
    }

    #[Test]
    public function non_null_non_empty_passes_through(): void
    {
        $p = new DefaultValueProcessor(default: 'fallback');
        self::assertSame('actual', $p->transform('actual', $this->context()));
        self::assertSame(0, $p->transform(0, $this->context()));
        self::assertFalse($p->transform(false, $this->context()));
    }

    #[Test]
    public function default_can_be_any_type(): void
    {
        $p = new DefaultValueProcessor(default: ['a', 'b']);
        self::assertSame(['a', 'b'], $p->transform(null, $this->context()));
    }

    private function context(): ProcessContext
    {
        return new ProcessContext(
            sourceRecord: new SourceRecord('csv', []),
            migrationId: 'm1',
            destinationField: 'status',
            lookup: static fn (string $m, SourceId $id): ?WriteResult => null,
        );
    }
}
