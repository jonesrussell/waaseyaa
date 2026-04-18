<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Tests\Unit\Sync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\NorthCloud\Sync\NcSyncResult;

#[CoversClass(NcSyncResult::class)]
final class NcSyncResultTest extends TestCase
{
    #[Test]
    public function defaultResultIsZeroed(): void
    {
        $r = new NcSyncResult();

        $this->assertSame(0, $r->created);
        $this->assertSame(0, $r->skipped);
        $this->assertSame(0, $r->failed);
        $this->assertFalse($r->fetchFailed);
        $this->assertSame(0, $r->fetched);
        $this->assertSame([], $r->skipReasons);
        $this->assertSame([], $r->createdSamples);
        $this->assertSame([], $r->skippedSamples);
    }

    #[Test]
    public function withHelpersProduceNewImmutableInstances(): void
    {
        $a = new NcSyncResult();
        $b = $a->withCreated()->withCreated(2)->withSkipped()->withFailed(3);

        $this->assertSame(0, $a->created, 'original untouched');
        $this->assertSame(3, $b->created);
        $this->assertSame(1, $b->skipped);
        $this->assertSame(3, $b->failed);
        $this->assertFalse($b->fetchFailed);
    }

    #[Test]
    public function withFetchFailedFlagsResult(): void
    {
        $r = (new NcSyncResult())->withFetchFailed();

        $this->assertTrue($r->fetchFailed);
    }

    #[Test]
    public function toArrayIncludesCountersAndObservabilityFields(): void
    {
        $r = (new NcSyncResult())
            ->withCreated(4)
            ->withSkipped(2)
            ->withFailed(1)
            ->withFetched(8)
            ->withSkipReason('missing_regional_signal', 2)
            ->withCreatedSample(['title' => 'A'], 10)
            ->withSkippedSample(['title' => 'B', 'reason' => 'missing_regional_signal'], 10);

        $this->assertSame(
            [
                'created' => 4,
                'skipped' => 2,
                'failed' => 1,
                'fetch_failed' => false,
                'fetched' => 8,
                'skip_reasons' => ['missing_regional_signal' => 2],
                'created_samples' => [['title' => 'A']],
                'skipped_samples' => [['title' => 'B', 'reason' => 'missing_regional_signal']],
            ],
            $r->toArray(),
        );
    }
}
