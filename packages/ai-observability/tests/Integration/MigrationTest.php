<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

#[CoversNothing]
final class MigrationTest extends TestCase
{
    #[Test]
    public function upCreatesTraceSpanTable(): void
    {
        $db = DBALDatabase::createSqlite();
        $schema = new SchemaBuilder($db->getConnection());

        $migration = require __DIR__ . '/../../migrations/2026_04_14_000001_create_trace_span_table.php';
        $migration->up($schema);

        $this->assertTrue($schema->hasTable('trace_span'));
        $this->assertTrue($schema->hasColumn('trace_span', 'uuid'));
        $this->assertTrue($schema->hasColumn('trace_span', 'trace_uuid'));
        $this->assertTrue($schema->hasColumn('trace_span', 'kind'));
        $this->assertTrue($schema->hasColumn('trace_span', 'attributes'));
    }

    #[Test]
    public function downDropsTraceSpanTable(): void
    {
        $db = DBALDatabase::createSqlite();
        $schema = new SchemaBuilder($db->getConnection());

        $migration = require __DIR__ . '/../../migrations/2026_04_14_000001_create_trace_span_table.php';
        $migration->up($schema);

        $this->assertTrue($schema->hasTable('trace_span'));

        $migration->down($schema);

        $this->assertFalse($schema->hasTable('trace_span'));
    }
}
