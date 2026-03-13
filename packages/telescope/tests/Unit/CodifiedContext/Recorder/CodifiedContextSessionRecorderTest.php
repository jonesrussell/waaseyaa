<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\Tests\Unit\CodifiedContext\Recorder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Telescope\CodifiedContext\Recorder\CodifiedContextSessionRecorder;
use Waaseyaa\Telescope\Storage\SqliteTelescopeStore;

#[CoversClass(CodifiedContextSessionRecorder::class)]
final class CodifiedContextSessionRecorderTest extends TestCase
{
    private SqliteTelescopeStore $store;
    private CodifiedContextSessionRecorder $recorder;

    protected function setUp(): void
    {
        $this->store = SqliteTelescopeStore::createInMemory();
        $this->recorder = new CodifiedContextSessionRecorder(store: $this->store);
    }

    #[Test]
    public function typeConstantIsCcSession(): void
    {
        $this->assertSame('cc_session', CodifiedContextSessionRecorder::TYPE);
    }

    #[Test]
    public function recordStartStoresSessionStartEvent(): void
    {
        $this->recorder->recordStart(
            sessionId: 'sess-abc',
            repoHash: 'abc123',
            metadata: ['branch' => 'main'],
        );

        $entries = $this->store->query(CodifiedContextSessionRecorder::TYPE);
        $this->assertCount(1, $entries);

        $data = $entries[0]->data;
        $this->assertSame('sess-abc', $data['session_id']);
        $this->assertSame('session_start', $data['event_type']);
        $this->assertSame('abc123', $data['repo_hash']);
        $this->assertSame(['branch' => 'main'], $data['metadata']);
        $this->assertArrayHasKey('occurred_at', $data);
    }

    #[Test]
    public function recordStartWithEmptyMetadata(): void
    {
        $this->recorder->recordStart(sessionId: 'sess-1', repoHash: 'deadbeef');

        $entries = $this->store->query(CodifiedContextSessionRecorder::TYPE);
        $data = $entries[0]->data;

        $this->assertSame([], $data['metadata']);
    }

    #[Test]
    public function recordEndStoresSessionEndEvent(): void
    {
        $this->recorder->recordEnd(
            sessionId: 'sess-abc',
            durationMs: 1234.5,
            eventCount: 7,
        );

        $entries = $this->store->query(CodifiedContextSessionRecorder::TYPE);
        $this->assertCount(1, $entries);

        $data = $entries[0]->data;
        $this->assertSame('sess-abc', $data['session_id']);
        $this->assertSame('session_end', $data['event_type']);
        $this->assertSame(1234.5, $data['duration_ms']);
        $this->assertSame(7, $data['event_count']);
        $this->assertArrayHasKey('occurred_at', $data);
    }

    #[Test]
    public function startAndEndAreStoredIndependently(): void
    {
        $this->recorder->recordStart('sess-x', 'hash1');
        $this->recorder->recordEnd('sess-x', 500.0, 3);

        $entries = $this->store->query(CodifiedContextSessionRecorder::TYPE);
        $this->assertCount(2, $entries);

        $eventTypes = array_column(array_map(fn ($e) => $e->data, $entries), 'event_type');
        $this->assertContains('session_start', $eventTypes);
        $this->assertContains('session_end', $eventTypes);
    }
}
