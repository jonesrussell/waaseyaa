<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\Hydration\HydrationContext;
use Waaseyaa\Entity\Snapshot\EntityValuesSnapshot;

#[CoversClass(EntityValuesSnapshot::class)]
final class EntityValuesSnapshotTest extends TestCase
{
    #[Test]
    public function fromEntityCapturesShallowStorageBag(): void
    {
        $entity = new TestEntity(
            values: ['id' => 1, 'label' => 'Hi', 'extra' => 'x'],
            entityTypeId: 'test_entity',
            entityKeys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label'],
        );
        $ctx = new HydrationContext('test_entity', ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label']);
        $snap = EntityValuesSnapshot::fromEntity($entity, $ctx);

        $this->assertTrue($snap->has('label'));
        $this->assertSame('Hi', $snap->get('label'));
        $this->assertSame('x', $snap->get('extra'));
        $this->assertSame($ctx, $snap->context());
    }

    #[Test]
    public function toStorageArrayReturnsIndependentTopLevelBag(): void
    {
        $entity = new TestEntity(
            values: ['id' => 1, 'label' => 'A'],
            entityTypeId: 'test_entity',
            entityKeys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label'],
        );
        $snap = EntityValuesSnapshot::fromEntity(
            $entity,
            new HydrationContext('t', ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label']),
        );
        $out = $snap->toStorageArray();
        $out['label'] = 'Mutated';

        $this->assertSame('A', $entity->label());
    }

    #[Test]
    public function getCastAwareThrowsWhenNoCastMapProvided(): void
    {
        $entity = new TestEntity(
            values: ['id' => 1],
            entityTypeId: 'test_entity',
            entityKeys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label'],
        );
        $snap = EntityValuesSnapshot::fromEntity(
            $entity,
            new HydrationContext('t', ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label']),
        );

        $this->expectException(\LogicException::class);
        $snap->getCastAware('n');
    }

    #[Test]
    public function getCastAwareUsesValueCasterWhenCastMapInjected(): void
    {
        $entity = new class (['title' => 't', 'n' => '7']) extends \Waaseyaa\Entity\ContentEntityBase {
            protected array $casts = ['n' => 'int'];

            public function __construct(array $values = [])
            {
                parent::__construct($values, 'article', ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'bundle']);
            }
        };
        $snap = EntityValuesSnapshot::fromEntity(
            $entity,
            new HydrationContext('article', ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'bundle']),
            casts: ['n' => 'int'],
        );

        $this->assertSame(7, $snap->getCastAware('n'));
        $this->assertSame('7', $snap->get('n'));
    }

    #[Test]
    public function fromEntityAndTypeBuildsContextFromEntityType(): void
    {
        $type = new EntityType(
            id: 'node',
            label: 'Node',
            class: TestEntity::class,
            keys: ['id' => 'nid', 'label' => 'title'],
        );
        $entity = new TestEntity(
            values: ['nid' => 5, 'title' => 'X'],
            entityTypeId: 'node',
            entityKeys: ['id' => 'nid', 'label' => 'title'],
        );
        $snap = EntityValuesSnapshot::fromEntityAndType($entity, $type);

        $this->assertSame('node', $snap->context()->entityTypeId);
        $this->assertSame(['id' => 'nid', 'label' => 'title'], $snap->context()->entityKeys);
        $this->assertSame('X', $snap->get('title'));
    }
}
