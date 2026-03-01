<?php

declare(strict_types=1);

namespace Waaseyaa\Routing\Tests\Unit;

use Waaseyaa\Routing\WaaseyaaRouter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;

#[CoversClass(WaaseyaaRouter::class)]
final class WaaseyaaRouterTest extends TestCase
{
    #[Test]
    public function matchReturnsParametersForStaticRoute(): void
    {
        $router = new WaaseyaaRouter();
        $router->addRoute('home', new Route('/'));

        $result = $router->match('/');

        $this->assertSame('home', $result['_route']);
    }

    #[Test]
    public function matchReturnsParametersForDynamicRoute(): void
    {
        $router = new WaaseyaaRouter();
        $router->addRoute('node.view', new Route('/node/{id}', [], ['id' => '\d+']));

        $result = $router->match('/node/42');

        $this->assertSame('node.view', $result['_route']);
        $this->assertSame('42', $result['id']);
    }

    #[Test]
    public function matchThrowsForUnknownPath(): void
    {
        $router = new WaaseyaaRouter();
        $router->addRoute('home', new Route('/'));

        $this->expectException(ResourceNotFoundException::class);
        $router->match('/unknown');
    }

    #[Test]
    public function matchRespectsRequirements(): void
    {
        $router = new WaaseyaaRouter();
        $router->addRoute('node.view', new Route('/node/{id}', [], ['id' => '\d+']));

        // Non-numeric ID should not match.
        $this->expectException(ResourceNotFoundException::class);
        $router->match('/node/abc');
    }

    #[Test]
    public function generateProducesUrlForStaticRoute(): void
    {
        $router = new WaaseyaaRouter();
        $router->addRoute('home', new Route('/'));

        $url = $router->generate('home');

        $this->assertSame('/', $url);
    }

    #[Test]
    public function generateProducesUrlWithParameters(): void
    {
        $router = new WaaseyaaRouter();
        $router->addRoute('node.view', new Route('/node/{id}'));

        $url = $router->generate('node.view', ['id' => 42]);

        $this->assertSame('/node/42', $url);
    }

    #[Test]
    public function generateAddsExtraParametersAsQueryString(): void
    {
        $router = new WaaseyaaRouter();
        $router->addRoute('node.view', new Route('/node/{id}'));

        $url = $router->generate('node.view', ['id' => 42, 'tab' => 'edit']);

        $this->assertSame('/node/42?tab=edit', $url);
    }

    #[Test]
    public function getRouteCollectionReturnsAllRoutes(): void
    {
        $router = new WaaseyaaRouter();
        $router->addRoute('home', new Route('/'));
        $router->addRoute('about', new Route('/about'));

        $collection = $router->getRouteCollection();

        $this->assertCount(2, $collection);
        $this->assertNotNull($collection->get('home'));
        $this->assertNotNull($collection->get('about'));
    }

    #[Test]
    public function addingRouteResetsMatcherAndGenerator(): void
    {
        $router = new WaaseyaaRouter();
        $router->addRoute('home', new Route('/'));

        // Trigger matcher/generator creation.
        $router->match('/');
        $router->generate('home');

        // Add new route — should work with fresh matcher.
        $router->addRoute('about', new Route('/about'));

        $result = $router->match('/about');
        $this->assertSame('about', $result['_route']);

        $url = $router->generate('about');
        $this->assertSame('/about', $url);
    }

    #[Test]
    public function acceptsCustomRequestContext(): void
    {
        $context = new RequestContext();
        $context->setBaseUrl('/app');

        $router = new WaaseyaaRouter($context);
        $router->addRoute('home', new Route('/'));

        $url = $router->generate('home');

        $this->assertSame('/app/', $url);
    }

    #[Test]
    public function matchReturnsDefaultValues(): void
    {
        $router = new WaaseyaaRouter();
        $router->addRoute('list', new Route('/items/{page}', ['page' => '1']));

        $result = $router->match('/items');

        $this->assertSame('list', $result['_route']);
        $this->assertSame('1', $result['page']);
    }

    #[Test]
    public function matchMultipleParameterRoute(): void
    {
        $router = new WaaseyaaRouter();
        $router->addRoute(
            'node.revision',
            new Route('/node/{node}/revision/{revision}', [], ['node' => '\d+', 'revision' => '\d+'])
        );

        $result = $router->match('/node/5/revision/10');

        $this->assertSame('5', $result['node']);
        $this->assertSame('10', $result['revision']);
    }
}
