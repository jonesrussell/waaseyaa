<?php

declare(strict_types=1);

namespace Waaseyaa\Access\Tests\Unit\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Policy\RevisionPolicyComposition;
use Waaseyaa\Entity\EntityInterface;

/**
 * M-004 WP05 — Two-axis policy fallback semantics.
 *
 *  - FR-021 / ADR 016 FR-040: missing `view_revision` falls back to `view`.
 *  - FR-022 / ADR 017:        missing `translate` falls back to `edit`.
 *
 * Fallback is only triggered when the policy returns `Neutral`. Explicit
 * `Forbidden`, `Unauthenticated`, and `Allowed` short-circuit before fallback.
 */
#[CoversClass(RevisionPolicyComposition::class)]
final class TwoAxisPolicyFallbackTest extends TestCase
{
    #[Test]
    public function viewRevisionFallsBackToViewWhenPolicyIsNeutral(): void
    {
        $policy = $this->policyDeclaringOnly('view', AccessResult::allowed('view granted'));

        $composer = new RevisionPolicyComposition();
        $result   = $composer->composeAccess(
            $policy,
            $this->makeEntity(),
            $this->makeAccount(),
            'view_revision',
        );

        self::assertTrue($result->isAllowed());
        self::assertSame('view granted', $result->reason);
        self::assertSame(['view_revision', 'view'], $policy->seenOperations);
    }

    #[Test]
    public function translateFallsBackToEditWhenPolicyIsNeutral(): void
    {
        $policy = $this->policyDeclaringOnly('edit', AccessResult::allowed('edit granted'));

        $composer = new RevisionPolicyComposition();
        $result   = $composer->composeAccess(
            $policy,
            $this->makeEntity(),
            $this->makeAccount(),
            'translate',
        );

        self::assertTrue($result->isAllowed());
        self::assertSame('edit granted', $result->reason);
        self::assertSame(['translate', 'edit'], $policy->seenOperations);
    }

    #[Test]
    public function explicitForbiddenOnViewRevisionDoesNotFallBackToView(): void
    {
        $policy = $this->policyReturning([
            'view_revision' => AccessResult::forbidden('locked'),
            'view'          => AccessResult::allowed('open by default'),
        ]);

        $composer = new RevisionPolicyComposition();
        $result   = $composer->composeAccess(
            $policy,
            $this->makeEntity(),
            $this->makeAccount(),
            'view_revision',
        );

        self::assertTrue($result->isForbidden());
        self::assertSame('locked', $result->reason);
        self::assertSame(['view_revision'], $policy->seenOperations);
    }

    #[Test]
    public function explicitForbiddenOnTranslateDoesNotFallBackToEdit(): void
    {
        $policy = $this->policyReturning([
            'translate' => AccessResult::forbidden('translation locked'),
            'edit'      => AccessResult::allowed('edit allowed for role'),
        ]);

        $composer = new RevisionPolicyComposition();
        $result   = $composer->composeAccess(
            $policy,
            $this->makeEntity(),
            $this->makeAccount(),
            'translate',
        );

        self::assertTrue($result->isForbidden());
        self::assertSame('translation locked', $result->reason);
        self::assertSame(['translate'], $policy->seenOperations);
    }

    #[Test]
    public function unauthenticatedShortCircuitsFallback(): void
    {
        $policy = $this->policyReturning([
            'view_revision' => AccessResult::unauthenticated('401'),
            'view'          => AccessResult::allowed('view always allowed'),
        ]);

        $composer = new RevisionPolicyComposition();
        $result   = $composer->composeAccess(
            $policy,
            $this->makeEntity(),
            $this->makeAccount(),
            'view_revision',
        );

        self::assertTrue($result->isUnauthenticated());
        self::assertSame(['view_revision'], $policy->seenOperations);
    }

    #[Test]
    public function nonRoutedOperationsHaveNoFallback(): void
    {
        $policy = $this->policyReturning([
            'view'   => AccessResult::neutral(),
            'edit'   => AccessResult::allowed('would-be fallback'),
            'delete' => AccessResult::neutral(),
        ]);

        $composer = new RevisionPolicyComposition();

        foreach (['view', 'edit', 'delete'] as $operation) {
            $policy->reset();
            $result = $composer->composeAccess(
                $policy,
                $this->makeEntity(),
                $this->makeAccount(),
                $operation,
            );

            // The contract only defines fallbacks for `view_revision` and `translate`.
            self::assertSame(
                [$operation],
                $policy->seenOperations,
                "operation `{$operation}` must not trigger a contract-level fallback",
            );
            // Verify result matches the primary call exactly (no fallback rewrite).
            self::assertSame($operation === 'edit' ? 'would-be fallback' : '', $result->reason);
        }
    }

    private function makeEntity(): EntityInterface
    {
        return new class implements EntityInterface {
            public function id(): int|string|null
            {
                return 1;
            }

            public function uuid(): string
            {
                return '00000000-0000-4000-8000-000000000001';
            }

            public function label(): string
            {
                return 'thing';
            }

            public function getEntityTypeId(): string
            {
                return 'thing';
            }

            public function bundle(): string
            {
                return 'thing';
            }

            public function isNew(): bool
            {
                return false;
            }

            public function get(string $name): mixed
            {
                return null;
            }

            public function set(string $name, mixed $value): static
            {
                return $this;
            }

            public function toArray(): array
            {
                return [];
            }

            public function language(): string
            {
                return 'en';
            }
        };
    }

    private function makeAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int|string
            {
                return 1;
            }

            public function hasPermission(string $permission): bool
            {
                return false;
            }

            public function getRoles(): array
            {
                return [];
            }

            public function isAuthenticated(): bool
            {
                return true;
            }
        };
    }

    /**
     * Policy that only opines on a single operation, returns `Neutral` for everything else.
     *
     * Models a policy that "does NOT explicitly declare view_revision" — when asked about
     * `view_revision` or `translate`, it returns `Neutral`, which the composer routes to
     * the fallback operation (`view` / `edit`).
     */
    private function policyDeclaringOnly(string $declaredOperation, AccessResult $result): object
    {
        return new class($declaredOperation, $result) implements AccessPolicyInterface {
            /**
             * @var list<string>
             */
            public array $seenOperations = [];

            public function __construct(
                private readonly string $declared,
                private readonly AccessResult $result,
            ) {}

            public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
            {
                $this->seenOperations[] = $operation;

                return $operation === $this->declared
                    ? $this->result
                    : AccessResult::neutral();
            }

            public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
            {
                return AccessResult::neutral();
            }

            public function appliesTo(string $entityTypeId): bool
            {
                return true;
            }
        };
    }

    /**
     * Policy with explicit per-operation return values.
     *
     * @param array<string, AccessResult> $byOperation
     */
    private function policyReturning(array $byOperation): object
    {
        return new class($byOperation) implements AccessPolicyInterface {
            /**
             * @var list<string>
             */
            public array $seenOperations = [];

            /**
             * @param array<string, AccessResult> $byOperation
             */
            public function __construct(private readonly array $byOperation) {}

            public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
            {
                $this->seenOperations[] = $operation;

                return $this->byOperation[$operation] ?? AccessResult::neutral();
            }

            public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
            {
                return AccessResult::neutral();
            }

            public function appliesTo(string $entityTypeId): bool
            {
                return true;
            }

            public function reset(): void
            {
                $this->seenOperations = [];
            }
        };
    }
}
