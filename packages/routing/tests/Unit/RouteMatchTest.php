<?php

declare(strict_types=1);

namespace Aurora\Routing\Tests\Unit;

use Aurora\Routing\RouteMatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;

#[CoversClass(RouteMatch::class)]
final class RouteMatchTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $route = new Route('/test');
        $params = ['id' => '42', 'slug' => 'hello'];

        $match = new RouteMatch('test_route', $route, $params);

        $this->assertSame('test_route', $match->routeName);
        $this->assertSame($route, $match->route);
        $this->assertSame($params, $match->parameters);
    }

    #[Test]
    public function defaultParametersAreEmpty(): void
    {
        $route = new Route('/test');
        $match = new RouteMatch('test_route', $route);

        $this->assertSame([], $match->parameters);
    }

    #[Test]
    public function getParameterReturnsValue(): void
    {
        $route = new Route('/test');
        $match = new RouteMatch('test_route', $route, ['id' => '42']);

        $this->assertSame('42', $match->getParameter('id'));
    }

    #[Test]
    public function getParameterReturnsNullForMissing(): void
    {
        $route = new Route('/test');
        $match = new RouteMatch('test_route', $route, ['id' => '42']);

        $this->assertNull($match->getParameter('nonexistent'));
    }

    #[Test]
    public function getParameterReturnsNullValueDistinctFromMissing(): void
    {
        $route = new Route('/test');
        $match = new RouteMatch('test_route', $route, ['key' => null]);

        $this->assertNull($match->getParameter('key'));
        $this->assertTrue($match->hasParameter('key'));
    }

    #[Test]
    public function hasParameterReturnsTrueWhenPresent(): void
    {
        $route = new Route('/test');
        $match = new RouteMatch('test_route', $route, ['id' => '42']);

        $this->assertTrue($match->hasParameter('id'));
    }

    #[Test]
    public function hasParameterReturnsFalseWhenMissing(): void
    {
        $route = new Route('/test');
        $match = new RouteMatch('test_route', $route, ['id' => '42']);

        $this->assertFalse($match->hasParameter('nonexistent'));
    }

    #[Test]
    public function hasParameterReturnsTrueForNullValue(): void
    {
        $route = new Route('/test');
        $match = new RouteMatch('test_route', $route, ['key' => null]);

        $this->assertTrue($match->hasParameter('key'));
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new \ReflectionClass(RouteMatch::class);
        $this->assertTrue($reflection->isReadOnly());
    }
}
