<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\Tests\Unit\CodifiedContext\Recorder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Telescope\CodifiedContext\Recorder\CodifiedContextValidationRecorder;
use Waaseyaa\Telescope\Storage\SqliteTelescopeStore;

#[CoversClass(CodifiedContextValidationRecorder::class)]
final class CodifiedContextValidationRecorderTest extends TestCase
{
    private SqliteTelescopeStore $store;
    private CodifiedContextValidationRecorder $recorder;

    protected function setUp(): void
    {
        $this->store = SqliteTelescopeStore::createInMemory();
        $this->recorder = new CodifiedContextValidationRecorder(store: $this->store);
    }

    #[Test]
    public function typeConstantIsCcValidation(): void
    {
        $this->assertSame('cc_validation', CodifiedContextValidationRecorder::TYPE);
    }

    #[Test]
    public function recordStoresValidationReport(): void
    {
        $this->recorder->record(
            sessionId: 'sess-v1',
            driftScore: 15,
            components: ['constitution' => 'ok', 'skills' => 'stale'],
            issues: ['skills/waaseyaa/entity-system.md is 3 versions behind'],
            recommendation: 'Update entity-system skill',
        );

        $entries = $this->store->query(CodifiedContextValidationRecorder::TYPE);
        $this->assertCount(1, $entries);

        $data = $entries[0]->data;
        $this->assertSame('sess-v1', $data['session_id']);
        $this->assertSame('validation_report', $data['event_type']);
        $this->assertSame(15, $data['drift_score']);
        $this->assertSame(['constitution' => 'ok', 'skills' => 'stale'], $data['components']);
        $this->assertSame(['skills/waaseyaa/entity-system.md is 3 versions behind'], $data['issues']);
        $this->assertSame('Update entity-system skill', $data['recommendation']);
        $this->assertArrayHasKey('occurred_at', $data);
    }

    #[Test]
    public function recordWithEmptyIssuesAndComponents(): void
    {
        $this->recorder->record(
            sessionId: 'sess-v2',
            driftScore: 0,
            components: [],
            issues: [],
            recommendation: 'No action required',
        );

        $entries = $this->store->query(CodifiedContextValidationRecorder::TYPE);
        $data = $entries[0]->data;

        $this->assertSame(0, $data['drift_score']);
        $this->assertSame([], $data['components']);
        $this->assertSame([], $data['issues']);
    }

    #[Test]
    public function multipleRecordsAreStoredSeparately(): void
    {
        $this->recorder->record('sess-a', 10, [], [], 'ok');
        $this->recorder->record('sess-b', 20, [], [], 'update');

        $entries = $this->store->query(CodifiedContextValidationRecorder::TYPE);
        $this->assertCount(2, $entries);
    }
}
