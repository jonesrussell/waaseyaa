<?php

declare(strict_types=1);

namespace Waaseyaa\User\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\User\User;
use Waaseyaa\User\UserAccessPolicy;

#[CoversClass(UserAccessPolicy::class)]
final class UserAccessPolicyTest extends TestCase
{
    private UserAccessPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new UserAccessPolicy();
    }

    // -----------------------------------------------------------------
    // Interface and appliesTo
    // -----------------------------------------------------------------

    public function testImplementsAccessPolicyInterface(): void
    {
        $this->assertInstanceOf(AccessPolicyInterface::class, $this->policy);
    }

    public function testIsFinal(): void
    {
        $reflection = new \ReflectionClass(UserAccessPolicy::class);
        $this->assertTrue($reflection->isFinal());
    }

    public function testAppliesToUser(): void
    {
        $this->assertTrue($this->policy->appliesTo('user'));
    }

    public function testDoesNotApplyToOtherEntityTypes(): void
    {
        $this->assertFalse($this->policy->appliesTo('node'));
        $this->assertFalse($this->policy->appliesTo('media'));
        $this->assertFalse($this->policy->appliesTo(''));
    }

    // -----------------------------------------------------------------
    // View: admin bypass
    // -----------------------------------------------------------------

    public function testViewWithAdminPermission(): void
    {
        $user = new User(['uid' => 5, 'name' => 'alice', 'status' => 1]);
        $account = $this->createAccount(1, ['administer users']);

        $result = $this->policy->access($user, 'view', $account);
        $this->assertTrue($result->isAllowed());
    }

    // -----------------------------------------------------------------
    // View: own account
    // -----------------------------------------------------------------

    public function testViewOwnAccountAllowed(): void
    {
        $user = new User(['uid' => 5, 'name' => 'alice', 'status' => 1]);
        $account = $this->createAccount(5, ['access user profiles']);

        $result = $this->policy->access($user, 'view', $account);
        $this->assertTrue($result->isAllowed());
    }

    // -----------------------------------------------------------------
    // View: other active account with permission
    // -----------------------------------------------------------------

    public function testViewOtherActiveAccountWithPermission(): void
    {
        $user = new User(['uid' => 5, 'name' => 'alice', 'status' => 1]);
        $account = $this->createAccount(10, ['access user profiles']);

        $result = $this->policy->access($user, 'view', $account);
        $this->assertTrue($result->isAllowed());
    }

    // -----------------------------------------------------------------
    // View: other active account without permission
    // -----------------------------------------------------------------

    public function testViewOtherActiveAccountWithoutPermission(): void
    {
        $user = new User(['uid' => 5, 'name' => 'alice', 'status' => 1]);
        $account = $this->createAccount(10, []);

        $result = $this->policy->access($user, 'view', $account);
        $this->assertTrue($result->isNeutral());
    }

    // -----------------------------------------------------------------
    // View: blocked account
    // -----------------------------------------------------------------

    public function testViewBlockedAccountDeniedForNonAdmin(): void
    {
        $user = new User(['uid' => 5, 'name' => 'alice', 'status' => 0]);
        $account = $this->createAccount(10, ['access user profiles']);

        $result = $this->policy->access($user, 'view', $account);
        $this->assertTrue($result->isNeutral());
    }

    public function testViewBlockedAccountAllowedForAdmin(): void
    {
        $user = new User(['uid' => 5, 'name' => 'alice', 'status' => 0]);
        $account = $this->createAccount(1, ['administer users']);

        $result = $this->policy->access($user, 'view', $account);
        $this->assertTrue($result->isAllowed());
    }

    // -----------------------------------------------------------------
    // Update: own account
    // -----------------------------------------------------------------

    public function testUpdateOwnAccount(): void
    {
        $user = new User(['uid' => 5, 'name' => 'alice']);
        $account = $this->createAccount(5, []);

        $result = $this->policy->access($user, 'update', $account);
        $this->assertTrue($result->isAllowed());
    }

    // -----------------------------------------------------------------
    // Update: other account
    // -----------------------------------------------------------------

    public function testUpdateOtherAccountWithAdminPermission(): void
    {
        $user = new User(['uid' => 5, 'name' => 'alice']);
        $account = $this->createAccount(1, ['administer users']);

        $result = $this->policy->access($user, 'update', $account);
        $this->assertTrue($result->isAllowed());
    }

    public function testUpdateOtherAccountWithoutAdminPermission(): void
    {
        $user = new User(['uid' => 5, 'name' => 'alice']);
        $account = $this->createAccount(10, []);

        $result = $this->policy->access($user, 'update', $account);
        $this->assertTrue($result->isNeutral());
    }

    // -----------------------------------------------------------------
    // Delete
    // -----------------------------------------------------------------

    public function testDeleteWithAdminPermission(): void
    {
        $user = new User(['uid' => 5, 'name' => 'alice']);
        $account = $this->createAccount(1, ['administer users']);

        $result = $this->policy->access($user, 'delete', $account);
        $this->assertTrue($result->isAllowed());
    }

    public function testDeleteWithoutAdminPermission(): void
    {
        $user = new User(['uid' => 5, 'name' => 'alice']);
        $account = $this->createAccount(10, []);

        $result = $this->policy->access($user, 'delete', $account);
        $this->assertTrue($result->isNeutral());
    }

    public function testDeleteOwnAccountDenied(): void
    {
        $user = new User(['uid' => 5, 'name' => 'alice']);
        $account = $this->createAccount(5, ['administer users']);

        $result = $this->policy->access($user, 'delete', $account);
        $this->assertTrue($result->isForbidden());
    }

    // -----------------------------------------------------------------
    // Create access
    // -----------------------------------------------------------------

    public function testCreateAccessWithAdminPermission(): void
    {
        $account = $this->createAccount(1, ['administer users']);

        $result = $this->policy->createAccess('user', 'user', $account);
        $this->assertTrue($result->isAllowed());
    }

    public function testCreateAccessWithoutAdminPermission(): void
    {
        $account = $this->createAccount(5, []);

        $result = $this->policy->createAccess('user', 'user', $account);
        $this->assertTrue($result->isNeutral());
    }

    // -----------------------------------------------------------------
    // Unknown operation
    // -----------------------------------------------------------------

    public function testUnknownOperationReturnsNeutral(): void
    {
        $user = new User(['uid' => 5, 'name' => 'alice']);
        $account = $this->createAccount(10, []);

        $result = $this->policy->access($user, 'unknown_op', $account);
        $this->assertTrue($result->isNeutral());
    }

    // -----------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------

    /** @param string[] $permissions */
    private function createAccount(int $id, array $permissions): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn($id);
        $account->method('hasPermission')->willReturnCallback(
            fn(string $permission): bool => \in_array($permission, $permissions, true),
        );

        return $account;
    }
}
