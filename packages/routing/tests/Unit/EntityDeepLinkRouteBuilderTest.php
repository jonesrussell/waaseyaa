<?php

declare(strict_types=1);

namespace Waaseyaa\Routing\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Routing\EntityDeepLinkRouteBuilder;

#[CoversClass(EntityDeepLinkRouteBuilder::class)]
final class EntityDeepLinkRouteBuilderTest extends TestCase
{
    #[Test]
    public function producesCorrectPath(): void
    {
        $route = EntityDeepLinkRouteBuilder::for('/edit', 'node')
            ->controller('App\Foo::view')
            ->build();

        $this->assertSame('/edit/node/{id}', $route->getPath());
    }

    #[Test]
    public function setsGetMethod(): void
    {
        $route = EntityDeepLinkRouteBuilder::for('/edit', 'node')
            ->controller('App\Foo::view')
            ->build();

        $this->assertSame(['GET'], $route->getMethods());
    }

    #[Test]
    public function setsControllerDefault(): void
    {
        $route = EntityDeepLinkRouteBuilder::for('/edit', 'node')
            ->controller('App\Foo::view')
            ->build();

        $this->assertSame('App\Foo::view', $route->getDefault('_controller'));
    }

    #[Test]
    public function setsEntityParameterOption(): void
    {
        $route = EntityDeepLinkRouteBuilder::for('/edit', 'node')
            ->controller('App\Foo::view')
            ->build();

        $parameters = $route->getOption('parameters');
        $this->assertIsArray($parameters);
        $this->assertSame(['type' => 'entity:node'], $parameters['id']);
    }

    #[Test]
    public function trailingSlashOnSegmentIsTrimmed(): void
    {
        $route = EntityDeepLinkRouteBuilder::for('/edit/', 'node')
            ->controller('App\Foo::view')
            ->build();

        $this->assertSame('/edit/node/{id}', $route->getPath());
    }

    #[Test]
    public function methodCanBeOverriddenAfterHelper(): void
    {
        $route = EntityDeepLinkRouteBuilder::for('/edit', 'node')
            ->controller('App\Foo::view')
            ->methods('POST')
            ->build();

        $this->assertSame(['POST'], $route->getMethods());
    }

    #[Test]
    public function differentSegmentAndEntityType(): void
    {
        $route = EntityDeepLinkRouteBuilder::for('/profile/edit', 'user')
            ->controller('App\Foo::edit')
            ->build();

        $this->assertSame('/profile/edit/user/{id}', $route->getPath());

        $parameters = $route->getOption('parameters');
        $this->assertIsArray($parameters);
        $this->assertSame(['type' => 'entity:user'], $parameters['id']);
    }

    #[Test]
    public function publicPropertiesAreAccessible(): void
    {
        $builder = EntityDeepLinkRouteBuilder::for('/edit', 'node');

        $this->assertSame('/edit', $builder->segment);
        $this->assertSame('node', $builder->entityTypeId);
    }

    #[Test]
    public function returnedRouteBuilderIsChainable(): void
    {
        $route = EntityDeepLinkRouteBuilder::for('/edit', 'node')
            ->controller('App\Foo::view')
            ->requireAuthentication()
            ->build();

        $this->assertTrue($route->getOption('_authenticated'));
        $this->assertSame('/edit/node/{id}', $route->getPath());
    }
}
