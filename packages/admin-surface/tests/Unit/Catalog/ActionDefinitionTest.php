<?php

declare(strict_types=1);

namespace Waaseyaa\AdminSurface\Tests\Unit\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AdminSurface\Catalog\ActionDefinition;

#[CoversClass(ActionDefinition::class)]
final class ActionDefinitionTest extends TestCase
{
    #[Test]
    public function minimalActionToArray(): void
    {
        $action = new ActionDefinition('publish', 'Publish');

        $array = $action->toArray();

        self::assertSame('publish', $array['id']);
        self::assertSame('Publish', $array['label']);
        self::assertSame('entity', $array['scope']);
        self::assertArrayNotHasKey('confirmation', $array);
        self::assertArrayNotHasKey('dangerous', $array);
    }

    #[Test]
    public function dangerousActionWithConfirmation(): void
    {
        $action = new ActionDefinition('delete', 'Delete');
        $action->confirm('Are you sure you want to delete this?')
            ->dangerous();

        $array = $action->toArray();

        self::assertSame('Are you sure you want to delete this?', $array['confirmation']);
        self::assertTrue($array['dangerous']);
    }

    #[Test]
    public function collectionScopeAction(): void
    {
        $action = new ActionDefinition('bulk_delete', 'Delete Selected');
        $action->collection()->dangerous();

        $array = $action->toArray();

        self::assertSame('collection', $array['scope']);
    }
}
