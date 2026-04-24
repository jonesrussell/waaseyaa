<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\Tests\Unit\CodifiedContext\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Api\CodifiedContext\CodifiedContextSessionRow;
use Waaseyaa\Telescope\CodifiedContext\Storage\CodifiedContextSessionStoreAdapter;
use Waaseyaa\Telescope\CodifiedContext\Storage\SqliteCodifiedContextStore;

/**
 * @covers \Waaseyaa\Telescope\CodifiedContext\Storage\CodifiedContextSessionStoreAdapter
 */
#[CoversClass(CodifiedContextSessionStoreAdapter::class)]
final class CodifiedContextSessionStoreAdapterTest extends TestCase
{
    #[Test]
    public function query_by_session_maps_entries_to_api_rows(): void
    {
        $inner = SqliteCodifiedContextStore::createInMemory();
        $inner->store('cc_session', [
            'session_id' => 's1',
            'event_type' => 'cc_session',
            'phase' => 'start',
        ]);

        $adapter = new CodifiedContextSessionStoreAdapter($inner);
        $rows = $adapter->queryBySession('s1', 10);

        self::assertCount(1, $rows);
        self::assertContainsOnlyInstancesOf(CodifiedContextSessionRow::class, $rows);
        self::assertSame('s1', $rows[0]->sessionId);
        self::assertSame('cc_session', $rows[0]->type);
    }
}
