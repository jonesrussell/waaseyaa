<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit\Runner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Migration\Runner\RunOptions;

#[CoversClass(RunOptions::class)]
final class RunOptionsTest extends TestCase
{
    #[Test]
    public function defaults_are_safe(): void
    {
        $options = new RunOptions();
        self::assertFalse($options->dryRun);
        self::assertFalse($options->haltOnError);
        self::assertNull($options->limit);
        self::assertNull($options->runId);
    }

    #[Test]
    public function negative_limit_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RunOptions(limit: -1);
    }

    #[Test]
    public function zero_limit_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RunOptions(limit: 0);
    }

    #[Test]
    public function positive_limit_accepted(): void
    {
        $options = new RunOptions(limit: 50);
        self::assertSame(50, $options->limit);
    }

    #[Test]
    public function empty_run_id_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RunOptions(runId: '');
    }

    #[Test]
    public function malformed_run_id_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RunOptions(runId: 'not-a-uuid');
    }

    #[Test]
    public function uuidv7_run_id_accepted(): void
    {
        $options = new RunOptions(runId: '019683d3-1234-7000-8123-456789abcdef');
        self::assertSame('019683d3-1234-7000-8123-456789abcdef', $options->runId);
    }
}
