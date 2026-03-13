<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\Tests\Unit\CodifiedContext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Telescope\CodifiedContext\CodifiedContextObserver;
use Waaseyaa\Telescope\CodifiedContext\Recorder\CodifiedContextEventRecorder;
use Waaseyaa\Telescope\CodifiedContext\Recorder\CodifiedContextSessionRecorder;
use Waaseyaa\Telescope\CodifiedContext\Recorder\CodifiedContextValidationRecorder;
use Waaseyaa\Telescope\Storage\SqliteTelescopeStore;

#[CoversClass(CodifiedContextObserver::class)]
final class CodifiedContextObserverTest extends TestCase
{
    private SqliteTelescopeStore $store;
    private CodifiedContextObserver $observer;

    protected function setUp(): void
    {
        $this->store = SqliteTelescopeStore::createInMemory();
        $this->observer = new CodifiedContextObserver(store: $this->store);
    }

    #[Test]
    public function recordSessionStartDelegatesToSessionRecorder(): void
    {
        $this->observer->recordSessionStart('sess-1', 'hash-abc', ['env' => 'test']);

        $entries = $this->store->query(CodifiedContextSessionRecorder::TYPE);
        $this->assertCount(1, $entries);

        $data = $entries[0]->data;
        $this->assertSame('sess-1', $data['session_id']);
        $this->assertSame('session_start', $data['event_type']);
        $this->assertSame('hash-abc', $data['repo_hash']);
    }

    #[Test]
    public function recordContextLoadDelegatesToEventRecorder(): void
    {
        $this->observer->recordContextLoad('sess-2', 'ctx-hash', ['CLAUDE.md'], 4096);

        $entries = $this->store->query(CodifiedContextEventRecorder::TYPE);
        $this->assertCount(1, $entries);

        $data = $entries[0]->data;
        $this->assertSame('sess-2', $data['session_id']);
        $this->assertSame('context_load', $data['event_type']);
        $this->assertSame(4096, $data['total_bytes']);
    }

    #[Test]
    public function recordModelOutputDelegatesToEventRecorder(): void
    {
        $this->observer->recordModelOutput('sess-3', 'out-hash', ['spec.md'], 512);

        $entries = $this->store->query(CodifiedContextEventRecorder::TYPE);
        $data = $entries[0]->data;

        $this->assertSame('model_output', $data['event_type']);
        $this->assertSame(512, $data['token_count']);
    }

    #[Test]
    public function recordDriftDelegatesToEventRecorder(): void
    {
        $this->observer->recordDrift('sess-4', 30, 'medium', ['issue1']);

        $entries = $this->store->query(CodifiedContextEventRecorder::TYPE);
        $data = $entries[0]->data;

        $this->assertSame('drift_detected', $data['event_type']);
        $this->assertSame(30, $data['drift_score']);
        $this->assertSame('medium', $data['severity']);
    }

    #[Test]
    public function recordValidationDelegatesToValidationRecorder(): void
    {
        $this->observer->recordValidation('sess-5', 8, ['tier1' => 'ok'], [], 'All clear');

        $entries = $this->store->query(CodifiedContextValidationRecorder::TYPE);
        $this->assertCount(1, $entries);

        $data = $entries[0]->data;
        $this->assertSame('sess-5', $data['session_id']);
        $this->assertSame('validation_report', $data['event_type']);
        $this->assertSame(8, $data['drift_score']);
        $this->assertSame('All clear', $data['recommendation']);
    }

    #[Test]
    public function recordSessionEndDelegatesToSessionRecorder(): void
    {
        $this->observer->recordSessionEnd('sess-6', 2500.0, 10);

        $entries = $this->store->query(CodifiedContextSessionRecorder::TYPE);
        $data = $entries[0]->data;

        $this->assertSame('session_end', $data['event_type']);
        $this->assertEqualsWithDelta(2500.0, $data['duration_ms'], 0.001);
        $this->assertSame(10, $data['event_count']);
    }

    #[Test]
    public function eachRecorderUsesItsOwnType(): void
    {
        $this->observer->recordSessionStart('sess-7', 'h1');
        $this->observer->recordContextLoad('sess-7', 'ctx', [], 0);
        $this->observer->recordValidation('sess-7', 0, [], [], 'ok');

        $this->assertCount(1, $this->store->query(CodifiedContextSessionRecorder::TYPE));
        $this->assertCount(1, $this->store->query(CodifiedContextEventRecorder::TYPE));
        $this->assertCount(1, $this->store->query(CodifiedContextValidationRecorder::TYPE));
    }
}
