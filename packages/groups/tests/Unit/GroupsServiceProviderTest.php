<?php

declare(strict_types=1);

namespace Waaseyaa\Groups\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Groups\Group;
use Waaseyaa\Groups\GroupsServiceProvider;
use Waaseyaa\Groups\GroupType;

#[CoversClass(GroupsServiceProvider::class)]
final class GroupsServiceProviderTest extends TestCase
{
    #[Test]
    public function registersGroupAndGroupType(): void
    {
        $provider = new GroupsServiceProvider();
        $provider->register();

        $entityTypes = $provider->getEntityTypes();

        self::assertCount(2, $entityTypes);

        $ids = array_map(static fn($t) => $t->id(), $entityTypes);
        self::assertContains('group', $ids);
        self::assertContains('group_type', $ids);

        $byId = [];
        foreach ($entityTypes as $t) {
            $byId[$t->id()] = $t;
        }
        self::assertSame(Group::class, $byId['group']->getClass());
        self::assertSame(GroupType::class, $byId['group_type']->getClass());
    }

    #[Test]
    public function groupIsMultiBundleKeyedByGidTypeName(): void
    {
        $provider = new GroupsServiceProvider();
        $provider->register();

        $group = null;
        foreach ($provider->getEntityTypes() as $t) {
            if ($t->id() === 'group') {
                $group = $t;
                break;
            }
        }
        self::assertNotNull($group);
        self::assertSame('group_type', $group->getBundleEntityType());

        $keys = $group->getKeys();
        self::assertSame('gid', $keys['id']);
        self::assertSame('uuid', $keys['uuid']);
        self::assertSame('type', $keys['bundle']);
        self::assertSame('name', $keys['label']);
        self::assertSame('langcode', $keys['langcode']);
    }

    #[Test]
    public function groupShipsWithZeroPreRegisteredBundleFields(): void
    {
        $provider = new GroupsServiceProvider();
        $provider->register();

        $group = null;
        foreach ($provider->getEntityTypes() as $t) {
            if ($t->id() === 'group') {
                $group = $t;
                break;
            }
        }
        self::assertNotNull($group);
        // Core field-definition map is empty; bundle fields are registered by
        // consumers via EntityTypeManager::addBundleFields().
        self::assertSame([], $group->getFieldDefinitions());
    }
}
