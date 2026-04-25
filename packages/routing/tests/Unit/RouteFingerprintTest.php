<?php

declare(strict_types=1);

namespace Waaseyaa\Routing\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\RouteFingerprint;

final class RouteFingerprintTest extends TestCase
{
    #[Test]
    public function hash_changes_when_entity_parameters_change(): void
    {
        $a = RouteBuilder::create('/todos/{todo}')
            ->entityParameter('todo', 'todo')
            ->build();
        $b = RouteBuilder::create('/todos/{todo}')
            ->entityParameter('todo', 'task')
            ->build();

        self::assertNotSame(RouteFingerprint::hash($a), RouteFingerprint::hash($b));
    }

    #[Test]
    public function hash_changes_when_bind_option_changes(): void
    {
        $a = RouteBuilder::create('/todos/{todo}')
            ->entityParameter('todo', 'todo')
            ->bind('todo', \stdClass::class)
            ->build();
        $b = RouteBuilder::create('/todos/{todo}')
            ->entityParameter('todo', 'todo')
            ->bind('todo', \Throwable::class)
            ->build();

        self::assertNotSame(RouteFingerprint::hash($a), RouteFingerprint::hash($b));
    }
}
