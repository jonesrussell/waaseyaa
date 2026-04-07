<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\EntitySchemaSync;
use Waaseyaa\EntityStorage\Tests\Fixtures\TestStorageEntity;

#[CoversClass(EntitySchemaSync::class)]
final class EntitySchemaSyncTest extends TestCase
{
    private DBALDatabase $database;

    protected function setUp(): void
    {
        $this->database = DBALDatabase::createSqlite();
    }

    #[Test]
    public function it_creates_tables_for_each_entity_type(): void
    {
        $widget = $this->makeEntityType('widget', 'Widget');
        $gadget = $this->makeEntityType('gadget', 'Gadget');

        $sync = new EntitySchemaSync($this->database);
        $sync->syncAll([$widget, $gadget]);

        $schema = $this->database->schema();
        $this->assertTrue($schema->tableExists('widget'));
        $this->assertTrue($schema->tableExists('gadget'));
    }

    #[Test]
    public function sync_all_is_idempotent(): void
    {
        $widget = $this->makeEntityType('widget', 'Widget');

        $sync = new EntitySchemaSync($this->database);
        $sync->syncAll([$widget]);
        $sync->syncAll([$widget]);

        $this->assertTrue($this->database->schema()->tableExists('widget'));
    }

    private function makeEntityType(string $id, string $label): EntityType
    {
        return new EntityType(
            id: $id,
            label: $label,
            class: TestStorageEntity::class,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'bundle' => 'bundle',
                'label' => 'label',
                'langcode' => 'langcode',
            ],
        );
    }
}
