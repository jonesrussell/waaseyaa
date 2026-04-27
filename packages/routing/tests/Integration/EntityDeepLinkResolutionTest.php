<?php

declare(strict_types=1);

namespace Waaseyaa\Routing\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Waaseyaa\Routing\EntityDeepLinkRouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Integration tests for EntityDeepLinkRouteBuilder + WaaseyaaRouter.
 *
 * Scope: route building, registration, and URL matching.
 *
 * Out-of-scope (require full kernel boot — see WP10 E2E tests):
 *   - Entity hydration via EntityParamConverter (needs EntityTypeManager)
 *   - 404 response when entity ID is not found in storage
 *   - 401/403 access-policy enforcement via AccessChecker
 *
 * These access-policy and param-conversion behaviours are deferred per DIR-003:
 * ship working partial coverage over fake-green stubs.
 */
#[CoversNothing]
final class EntityDeepLinkResolutionTest extends TestCase
{
    private WaaseyaaRouter $router;

    protected function setUp(): void
    {
        $this->router = new WaaseyaaRouter();
    }

    #[Test]
    public function routeIsAddedAndMatchedByRouter(): void
    {
        $route = EntityDeepLinkRouteBuilder::for('/edit', 'node')
            ->controller('App\Controller\NodeController::edit')
            ->build();

        $this->router->addRoute('node.edit', $route);

        $params = $this->router->match('/edit/node/1');

        $this->assertSame('node.edit', $params['_route']);
        $this->assertSame('1', $params['id']);
    }

    #[Test]
    public function matchedParamsIncludeControllerDefault(): void
    {
        $route = EntityDeepLinkRouteBuilder::for('/edit', 'node')
            ->controller('App\Controller\NodeController::edit')
            ->build();

        $this->router->addRoute('node.edit', $route);

        $params = $this->router->match('/edit/node/42');

        $this->assertSame('App\Controller\NodeController::edit', $params['_controller']);
        $this->assertSame('42', $params['id']);
    }

    #[Test]
    public function differentEntityTypeSegmentsAreDistinct(): void
    {
        $nodeRoute = EntityDeepLinkRouteBuilder::for('/edit', 'node')
            ->controller('App\Controller\NodeController::edit')
            ->build();

        $userRoute = EntityDeepLinkRouteBuilder::for('/edit', 'user')
            ->controller('App\Controller\UserController::edit')
            ->build();

        $this->router->addRoute('node.edit', $nodeRoute);
        $this->router->addRoute('user.edit', $userRoute);

        $nodeParams = $this->router->match('/edit/node/10');
        $userParams = $this->router->match('/edit/user/20');

        $this->assertSame('node.edit', $nodeParams['_route']);
        $this->assertSame('10', $nodeParams['id']);

        $this->assertSame('user.edit', $userParams['_route']);
        $this->assertSame('20', $userParams['id']);
    }

    #[Test]
    public function nonMatchingPathThrowsResourceNotFoundException(): void
    {
        $route = EntityDeepLinkRouteBuilder::for('/edit', 'node')
            ->controller('App\Controller\NodeController::edit')
            ->build();

        $this->router->addRoute('node.edit', $route);

        $this->expectException(ResourceNotFoundException::class);
        $this->router->match('/view/node/1');
    }

    #[Test]
    public function routeHasEntityParameterOptionSet(): void
    {
        $route = EntityDeepLinkRouteBuilder::for('/edit', 'node')
            ->controller('App\Controller\NodeController::edit')
            ->build();

        $this->router->addRoute('node.edit', $route);

        $collection = $this->router->getRouteCollection();
        $registeredRoute = $collection->get('node.edit');

        $this->assertNotNull($registeredRoute);
        $parameters = $registeredRoute->getOption('parameters');
        $this->assertIsArray($parameters);
        $this->assertSame(['type' => 'entity:node'], $parameters['id']);
    }

    // -------------------------------------------------------------------------
    // Access-policy and entity-hydration assertions require full kernel boot.
    // Marked as skipped per DIR-003 guidance — see WP10 E2E tests.
    // -------------------------------------------------------------------------

    #[Test]
    public function entityNotFoundReturns404SkippedRequiresKernelBoot(): void
    {
        $this->markTestSkipped('Requires full kernel boot with EntityTypeManager. See WP10 E2E test.');
    }

    #[Test]
    public function accessPolicyEnforcementSkippedRequiresKernelBoot(): void
    {
        $this->markTestSkipped('Requires full kernel boot with AccessChecker and session middleware. See WP10 E2E test.');
    }
}
