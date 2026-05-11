<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Tests\Unit\Workflow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Api\Workflow\WorkflowDryRunController;
use Waaseyaa\Workflows\EditorialWorkflowPreset;

#[CoversClass(WorkflowDryRunController::class)]
final class WorkflowDryRunControllerTest extends TestCase
{
    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * @param string[] $roles
     * @param string[] $permissions
     */
    private function makeAccount(int $uid, array $roles = [], array $permissions = []): AccountInterface
    {
        return new class ($uid, $roles, $permissions) implements AccountInterface {
            /**
             * @param string[] $roles
             * @param string[] $permissions
             */
            public function __construct(
                private readonly int $uid,
                private readonly array $roles,
                private readonly array $permissions,
            ) {}

            public function id(): int
            {
                return $this->uid;
            }

            public function hasPermission(string $permission): bool
            {
                // administrator role gets all permissions.
                if (\in_array('administrator', $this->roles, true)) {
                    return true;
                }

                return \in_array($permission, $this->permissions, true);
            }

            /** @return string[] */
            public function getRoles(): array
            {
                return $this->roles;
            }

            public function isAuthenticated(): bool
            {
                return $this->uid !== 0;
            }
        };
    }

    /**
     * @param (\Closure(int): ?AccountInterface)|null $accountResolver
     */
    private function controller(?\Closure $accountResolver = null): WorkflowDryRunController
    {
        return new WorkflowDryRunController(
            static fn(): array => [EditorialWorkflowPreset::create()],
            $accountResolver,
        );
    }

    // -----------------------------------------------------------------
    // Allowed transition (admin publishing draft->review)
    // -----------------------------------------------------------------

    #[Test]
    public function allowedTransitionReturns200ShapeWithAllowedTrue(): void
    {
        $admin = $this->makeAccount(42, ['administrator']);
        $controller = $this->controller(static fn(int $uid): ?AccountInterface => $uid === 42 ? $admin : null);

        $result = $controller->dryRun([
            'workflow_id'  => 'editorial',
            'bundle'       => 'article',
            'from_state'   => 'review',
            'to_state'     => 'published',
            'account_uid'  => 42,
        ]);

        self::assertArrayHasKey('data', $result);
        self::assertTrue($result['data']['allowed']);
        self::assertFalse($result['data']['forbidden']);
        self::assertFalse($result['data']['neutral']);
        self::assertSame('publish', $result['data']['transition_id']);
        self::assertSame('Publish', $result['data']['transition_label']);
        self::assertSame('publish article content', $result['data']['required_permission']);
    }

    // -----------------------------------------------------------------
    // Forbidden transition (anonymous account publishing)
    // -----------------------------------------------------------------

    #[Test]
    public function forbiddenTransitionReturns200WithForbiddenTrueAndReason(): void
    {
        // Anonymous: uid=0, role='anonymous', no permissions.
        $anonymous = $this->makeAccount(0, ['anonymous']);
        $controller = $this->controller(static fn(int $uid): ?AccountInterface => $uid === 0 ? $anonymous : null);

        $result = $controller->dryRun([
            'workflow_id'  => 'editorial',
            'bundle'       => 'article',
            'from_state'   => 'review',
            'to_state'     => 'published',
            'account_uid'  => 0,
        ]);

        self::assertArrayHasKey('data', $result);
        self::assertFalse($result['data']['allowed']);
        self::assertTrue($result['data']['forbidden']);
        self::assertNotNull($result['data']['reason'], 'Forbidden result must carry a reason string.');
    }

    // -----------------------------------------------------------------
    // Unknown workflow → 404
    // -----------------------------------------------------------------

    #[Test]
    public function unknownWorkflowReturns404(): void
    {
        $admin = $this->makeAccount(42, ['administrator']);
        $controller = $this->controller(static fn(int $uid): ?AccountInterface => $admin);

        $result = $controller->dryRun([
            'workflow_id'  => 'nonexistent',
            'bundle'       => 'article',
            'from_state'   => 'draft',
            'to_state'     => 'published',
            'account_uid'  => 42,
        ]);

        self::assertArrayHasKey('errors', $result);
        self::assertSame(404, $result['status']);
        self::assertStringContainsString('nonexistent', $result['errors'][0]['detail']);
    }

    // -----------------------------------------------------------------
    // Unknown account → 404
    // -----------------------------------------------------------------

    #[Test]
    public function unknownAccountReturns404(): void
    {
        $controller = $this->controller(static fn(int $uid): ?AccountInterface => null);

        $result = $controller->dryRun([
            'workflow_id'  => 'editorial',
            'bundle'       => 'article',
            'from_state'   => 'draft',
            'to_state'     => 'review',
            'account_uid'  => 999,
        ]);

        self::assertArrayHasKey('errors', $result);
        self::assertSame(404, $result['status']);
        self::assertStringContainsString('999', $result['errors'][0]['detail']);
    }

    // -----------------------------------------------------------------
    // Invalid payload → 422
    // -----------------------------------------------------------------

    #[Test]
    public function missingRequiredFieldsReturns422(): void
    {
        $controller = $this->controller();

        // Missing from_state and account_uid.
        $result = $controller->dryRun([
            'workflow_id' => 'editorial',
            'bundle'      => 'article',
            'to_state'    => 'published',
        ]);

        self::assertArrayHasKey('errors', $result);
        self::assertSame(422, $result['status']);
        self::assertStringContainsString('from_state', $result['errors'][0]['detail']);
        self::assertStringContainsString('account_uid', $result['errors'][0]['detail']);
    }

    #[Test]
    public function emptyPayloadReturns422(): void
    {
        $result = $this->controller()->dryRun([]);

        self::assertArrayHasKey('errors', $result);
        self::assertSame(422, $result['status']);
    }

    // -----------------------------------------------------------------
    // Invariant: invalid transition pair surfaces as Forbidden, not an exception
    // -----------------------------------------------------------------

    #[Test]
    public function invalidTransitionPairReturnsForbiddenNotException(): void
    {
        $admin = $this->makeAccount(42, ['administrator']);
        $controller = $this->controller(static fn(int $uid): ?AccountInterface => $admin);

        // published → review is not a defined transition in the editorial preset
        // (you can only go published → archived or published → draft via unpublish).
        $result = $controller->dryRun([
            'workflow_id'  => 'editorial',
            'bundle'       => 'article',
            'from_state'   => 'published',
            'to_state'     => 'review',
            'account_uid'  => 42,
        ]);

        // EditorialTransitionAccessResolver wraps RuntimeException → Forbidden.
        self::assertArrayHasKey('data', $result);
        self::assertTrue($result['data']['forbidden']);
        self::assertNotNull($result['data']['reason']);
    }
}
