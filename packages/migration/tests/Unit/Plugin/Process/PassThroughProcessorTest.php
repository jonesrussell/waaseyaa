<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit\Plugin\Process;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Migration\Plugin\Process\PassThroughProcessor;
use Waaseyaa\Migration\Plugin\ProcessContext;
use Waaseyaa\Migration\Plugin\ReservedPluginIds;
use Waaseyaa\Migration\Plugin\SourceRecord;
use Waaseyaa\Migration\Plugin\WriteResult;
use Waaseyaa\Migration\SourceId;

#[CoversClass(PassThroughProcessor::class)]
final class PassThroughProcessorTest extends TestCase
{
    #[Test]
    public function id_is_pass_through(): void
    {
        $p = new PassThroughProcessor('post_title');
        self::assertSame(ReservedPluginIds::PASS_THROUGH, $p->id());
        self::assertSame('stable', $p->stability());
    }

    #[Test]
    public function returns_value_from_named_source_field(): void
    {
        $p = new PassThroughProcessor('post_title');
        $ctx = $this->context(['post_title' => 'Hello']);

        self::assertSame('Hello', $p->transform(null, $ctx));
    }

    #[Test]
    public function returns_null_when_field_absent(): void
    {
        $p = new PassThroughProcessor('post_title');
        $ctx = $this->context(['other' => 'x']);

        self::assertNull($p->transform(null, $ctx));
    }

    #[Test]
    public function ignores_chained_value_and_reads_from_source(): void
    {
        $p = new PassThroughProcessor('post_title');
        $ctx = $this->context(['post_title' => 'fromSource']);

        self::assertSame('fromSource', $p->transform('chainedIn', $ctx));
    }

    #[Test]
    public function rejects_empty_source_field(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PassThroughProcessor('');
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function context(array $fields): ProcessContext
    {
        return new ProcessContext(
            sourceRecord: new SourceRecord('wp', $fields),
            migrationId: 'm1',
            destinationField: 'title',
            lookup: static fn (string $m, SourceId $id): ?WriteResult => null,
        );
    }
}
