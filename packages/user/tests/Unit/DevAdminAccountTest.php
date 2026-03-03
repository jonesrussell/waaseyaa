<?php

declare(strict_types=1);

namespace Waaseyaa\User\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\User\DevAdminAccount;

#[CoversClass(DevAdminAccount::class)]
final class DevAdminAccountTest extends TestCase
{
    #[Test]
    public function implements_account_interface(): void
    {
        $account = new DevAdminAccount();
        $this->assertInstanceOf(AccountInterface::class, $account);
    }

    #[Test]
    public function id_returns_sentinel_value(): void
    {
        $account = new DevAdminAccount();
        $this->assertSame(PHP_INT_MAX, $account->id());
    }

    #[Test]
    public function has_permission_always_returns_true(): void
    {
        $account = new DevAdminAccount();
        $this->assertTrue($account->hasPermission('administer nodes'));
        $this->assertTrue($account->hasPermission('access content'));
        $this->assertTrue($account->hasPermission('any random permission'));
    }

    #[Test]
    public function get_roles_returns_administrator(): void
    {
        $account = new DevAdminAccount();
        $this->assertSame(['administrator'], $account->getRoles());
    }

    #[Test]
    public function is_authenticated_returns_true(): void
    {
        $account = new DevAdminAccount();
        $this->assertTrue($account->isAuthenticated());
    }
}
