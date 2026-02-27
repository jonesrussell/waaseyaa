<?php

declare(strict_types=1);

namespace Aurora\Routing\Tests\Unit;

use Aurora\Access\AccessResult;
use Aurora\Access\AccountInterface;
use Aurora\Routing\AccessChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;

#[CoversClass(AccessChecker::class)]
final class AccessCheckerTest extends TestCase
{
    private AccessChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new AccessChecker();
    }

    // --- No requirements ---

    #[Test]
    public function noRequirementsReturnsNeutral(): void
    {
        $route = new Route('/test');
        $account = $this->createMock(AccountInterface::class);

        $result = $this->checker->check($route, $account);

        $this->assertTrue($result->isNeutral());
    }

    // --- _access_callback ---

    #[Test]
    public function accessCallbackTrueReturnsAllowed(): void
    {
        $route = new Route('/test');
        $route->setOption('_access_callback', true);
        $account = $this->createMock(AccountInterface::class);

        $result = $this->checker->check($route, $account);

        $this->assertTrue($result->isAllowed());
    }

    // --- _permission ---

    #[Test]
    public function permissionGrantedReturnsAllowed(): void
    {
        $route = new Route('/admin');
        $route->setOption('_permission', 'administer site');

        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')
            ->with('administer site')
            ->willReturn(true);

        $result = $this->checker->check($route, $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function permissionDeniedReturnsForbidden(): void
    {
        $route = new Route('/admin');
        $route->setOption('_permission', 'administer site');

        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')
            ->with('administer site')
            ->willReturn(false);

        $result = $this->checker->check($route, $account);

        $this->assertTrue($result->isForbidden());
    }

    // --- _role ---

    #[Test]
    public function roleMatchReturnsAllowed(): void
    {
        $route = new Route('/admin');
        $route->setOption('_role', 'administrator');

        $account = $this->createMock(AccountInterface::class);
        $account->method('getRoles')
            ->willReturn(['authenticated', 'administrator']);

        $result = $this->checker->check($route, $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function roleMismatchReturnsForbidden(): void
    {
        $route = new Route('/admin');
        $route->setOption('_role', 'administrator');

        $account = $this->createMock(AccountInterface::class);
        $account->method('getRoles')
            ->willReturn(['authenticated']);

        $result = $this->checker->check($route, $account);

        $this->assertTrue($result->isForbidden());
    }

    #[Test]
    public function multipleRolesAnyMatchReturnsAllowed(): void
    {
        $route = new Route('/admin');
        $route->setOption('_role', 'administrator, editor');

        $account = $this->createMock(AccountInterface::class);
        $account->method('getRoles')
            ->willReturn(['authenticated', 'editor']);

        $result = $this->checker->check($route, $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function multipleRolesNoneMatchReturnsForbidden(): void
    {
        $route = new Route('/admin');
        $route->setOption('_role', 'administrator, editor');

        $account = $this->createMock(AccountInterface::class);
        $account->method('getRoles')
            ->willReturn(['authenticated']);

        $result = $this->checker->check($route, $account);

        $this->assertTrue($result->isForbidden());
    }

    // --- Combined requirements (AND logic) ---

    #[Test]
    public function combinedPermissionAndRoleBothPassReturnsAllowed(): void
    {
        $route = new Route('/admin');
        $route->setOption('_permission', 'administer site');
        $route->setOption('_role', 'administrator');

        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')
            ->with('administer site')
            ->willReturn(true);
        $account->method('getRoles')
            ->willReturn(['administrator']);

        $result = $this->checker->check($route, $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function combinedPermissionPassesButRoleFailsReturnsForbidden(): void
    {
        $route = new Route('/admin');
        $route->setOption('_permission', 'administer site');
        $route->setOption('_role', 'administrator');

        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')
            ->with('administer site')
            ->willReturn(true);
        $account->method('getRoles')
            ->willReturn(['authenticated']);

        $result = $this->checker->check($route, $account);

        $this->assertTrue($result->isForbidden());
    }

    #[Test]
    public function combinedPermissionFailsButRolePassesReturnsForbidden(): void
    {
        $route = new Route('/admin');
        $route->setOption('_permission', 'administer site');
        $route->setOption('_role', 'administrator');

        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')
            ->with('administer site')
            ->willReturn(false);
        $account->method('getRoles')
            ->willReturn(['administrator']);

        $result = $this->checker->check($route, $account);

        $this->assertTrue($result->isForbidden());
    }

    #[Test]
    public function accessCallbackWithPermissionBothPass(): void
    {
        $route = new Route('/admin');
        $route->setOption('_access_callback', true);
        $route->setOption('_permission', 'administer site');

        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')
            ->with('administer site')
            ->willReturn(true);

        $result = $this->checker->check($route, $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function accessCallbackTrueWithPermissionDeniedReturnsForbidden(): void
    {
        $route = new Route('/admin');
        $route->setOption('_access_callback', true);
        $route->setOption('_permission', 'administer site');

        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')
            ->with('administer site')
            ->willReturn(false);

        $result = $this->checker->check($route, $account);

        // AND logic: _access_callback = allowed AND _permission = forbidden => forbidden
        $this->assertTrue($result->isForbidden());
    }
}
