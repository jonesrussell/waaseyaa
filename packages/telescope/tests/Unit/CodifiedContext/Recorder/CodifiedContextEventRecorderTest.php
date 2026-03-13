<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\Tests\Unit\CodifiedContext\Recorder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Telescope\CodifiedContext\Recorder\CodifiedContextEventRecorder;
use Waaseyaa\Telescope\Storage\SqliteTelescopeStore;

#[CoversClass(CodifiedContextEventRecorder::class)]
final class CodifiedContextEventRecorderTest extends TestCase
{
    private SqliteTelescopeStore $store;
    private CodifiedContextEventRecorder $recorder;

    protected function setUp(): void
    {
        $this->store = SqliteTelescopeStore::createInMemory();
        $this->recorder = new CodifiedContextEventRecorder(store: $this->store);
    }

    #[Test]
    public function typeConstantIsCcEvent(): void
    {
        $this->assertSame('cc_event', CodifiedContextEventRecorder::TYPE);
    }

    #[Test]
    public function recordContextLoadStoresCorrectData(): void
    {
        $this->recorder->recordContextLoad(
            sessionId: 'sess-1',
            contextHash: 'deadbeef',
            filePaths: ['CLAUDE.md', 'docs/specs/entity-system.md'],
            totalBytes: 8192,
        );

        $entries = $this->store->query(CodifiedContextEventRecorder::TYPE);
        $this->assertCount(1, $entries);

        $data = $entries[0]->data;
        $this->assertSame('sess-1', $data['session_id']);
        $this->assertSame('context_load', $data['event_type']);
        $this->assertSame('deadbeef', $data['context_hash']);
        $this->assertSame(['CLAUDE.md', 'docs/specs/entity-system.md'], $data['file_paths']);
        $this->assertSame(8192, $data['total_bytes']);
        $this->assertArrayHasKey('occurred_at', $data);
    }

    #[Test]
    public function recordContextHashStoresCorrectData(): void
    {
        $this->recorder->recordContextHash(
            sessionId: 'sess-2',
            contextHash: 'abc123',
            algorithm: 'sha256',
        );

        $entries = $this->store->query(CodifiedContextEventRecorder::TYPE);
        $data = $entries[0]->data;

        $this->assertSame('sess-2', $data['session_id']);
        $this->assertSame('context_hash', $data['event_type']);
        $this->assertSame('abc123', $data['context_hash']);
        $this->assertSame('sha256', $data['algorithm']);
    }

    #[Test]
    public function recordContextHashDefaultAlgorithmIsSha256(): void
    {
        $this->recorder->recordContextHash('sess-3', 'xyz789');

        $entries = $this->store->query(CodifiedContextEventRecorder::TYPE);
        $this->assertSame('sha256', $entries[0]->data['algorithm']);
    }

    #[Test]
    public function recordContextFailStoresCorrectData(): void
    {
        $this->recorder->recordContextFail(
            sessionId: 'sess-4',
            errorMessage: 'File not found',
            filePath: 'docs/specs/missing.md',
        );

        $entries = $this->store->query(CodifiedContextEventRecorder::TYPE);
        $data = $entries[0]->data;

        $this->assertSame('sess-4', $data['session_id']);
        $this->assertSame('context_fail', $data['event_type']);
        $this->assertSame('File not found', $data['error_message']);
        $this->assertSame('docs/specs/missing.md', $data['file_path']);
    }

    #[Test]
    public function recordContextFailWithoutFilePath(): void
    {
        $this->recorder->recordContextFail('sess-5', 'Unknown error');

        $entries = $this->store->query(CodifiedContextEventRecorder::TYPE);
        $data = $entries[0]->data;

        $this->assertSame('Unknown error', $data['error_message']);
        $this->assertArrayNotHasKey('file_path', $data);
    }

    #[Test]
    public function recordModelOutputStoresCorrectData(): void
    {
        $this->recorder->recordModelOutput(
            sessionId: 'sess-6',
            outputHash: 'out-hash',
            references: ['docs/specs/entity-system.md'],
            tokenCount: 1024,
        );

        $entries = $this->store->query(CodifiedContextEventRecorder::TYPE);
        $data = $entries[0]->data;

        $this->assertSame('sess-6', $data['session_id']);
        $this->assertSame('model_output', $data['event_type']);
        $this->assertSame('out-hash', $data['output_hash']);
        $this->assertSame(['docs/specs/entity-system.md'], $data['references']);
        $this->assertSame(1024, $data['token_count']);
    }

    #[Test]
    public function recordDriftDetectedStoresCorrectData(): void
    {
        $this->recorder->recordDriftDetected(
            sessionId: 'sess-7',
            driftScore: 42,
            severity: 'high',
            issues: ['stale spec: entity-system'],
        );

        $entries = $this->store->query(CodifiedContextEventRecorder::TYPE);
        $data = $entries[0]->data;

        $this->assertSame('sess-7', $data['session_id']);
        $this->assertSame('drift_detected', $data['event_type']);
        $this->assertSame(42, $data['drift_score']);
        $this->assertSame('high', $data['severity']);
        $this->assertSame(['stale spec: entity-system'], $data['issues']);
    }

    #[Test]
    public function recordDriftCorrectedStoresCorrectData(): void
    {
        $this->recorder->recordDriftCorrected(
            sessionId: 'sess-8',
            originalScore: 42,
            correctedScore: 5,
            corrections: ['updated entity-system spec'],
        );

        $entries = $this->store->query(CodifiedContextEventRecorder::TYPE);
        $data = $entries[0]->data;

        $this->assertSame('sess-8', $data['session_id']);
        $this->assertSame('drift_corrected', $data['event_type']);
        $this->assertSame(42, $data['original_score']);
        $this->assertSame(5, $data['corrected_score']);
        $this->assertSame(['updated entity-system spec'], $data['corrections']);
    }
}
