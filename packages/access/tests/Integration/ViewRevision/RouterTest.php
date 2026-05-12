<?php

declare(strict_types=1);

namespace Waaseyaa\Access\Tests\Integration\ViewRevision;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\GateInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Access\Gate\RevisionAccessRouter;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\RevisionMetadata;
use Waaseyaa\Entity\RevisionableEntityInterface;
use Waaseyaa\Foundation\Log\LoggerInterface;

/**
 * Integration tests for RevisionAccessRouter.
 *
 * Covers T050 requirements:
 * - Policy declares view_revision → custom rule wins (allowed and forbidden).
 * - Policy does NOT declare view_revision → falls back to view(); log line emitted.
 * - Anonymous + non-public entity → fallback returns forbidden iff view() returns forbidden.
 * - Default-deny regression: absence of view_revision op MUST NOT flip to deny.
 *
 * @see \Waaseyaa\Access\Gate\RevisionAccessRouter
 */
#[CoversClass(RevisionAccessRouter::class)]
#[CoversClass(PolicyAttribute::class)]
final class RouterTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helper factories
    // -------------------------------------------------------------------------

    private function makeRevision(): RevisionMetadata
    {
        return new RevisionMetadata(
            revisionCreatedAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
            revisionAuthor: 42,
            revisionLog: 'test revision',
        );
    }

    private function makeEntity(string $typeId = 'node', int|string|null $id = 1, int|string|null $vid = 10): RevisionableEntityInterface
    {
        return new class ($typeId, $id, $vid) implements RevisionableEntityInterface {
            public function __construct(
                private readonly string $typeId,
                private readonly int|string|null $entityId,
                private readonly int|string|null $revId,
            ) {}

            public function id(): int|string|null { return $this->entityId; }
            public function revisionId(): int|string|null { return $this->revId; }
            public function isCurrentRevision(): bool { return true; }
            public function revisionMetadata(): RevisionMetadata
            {
                return new RevisionMetadata(new \DateTimeImmutable());
            }
            public function getEntityTypeId(): string { return $this->typeId; }
            public function bundle(): string { return $this->typeId; }
            public function label(): string { return 'Test'; }
            public function isNew(): bool { return false; }
            public function uuid(): string { return 'test-uuid'; }
            public function get(string $field): mixed { return null; }
            public function set(string $field, mixed $value): static { return $this; }
            public function toArray(): array { return []; }
            public function language(): string { return 'en'; }
        };
    }

    private function makeAccount(bool $authenticated = true): AccountInterface
    {
        return new class ($authenticated) implements AccountInterface {
            public function __construct(private readonly bool $auth) {}
            public function id(): int { return $this->auth ? 99 : 0; }
            public function isAuthenticated(): bool { return $this->auth; }
            public function getRoles(): array { return []; }
            public function hasPermission(string $permission): bool { return false; }
        };
    }

    /**
     * Recording logger that captures all info() calls.
     *
     * Returns [logger, recordsHolder] where $recordsHolder->entries is updated in-place.
     *
     * @return array{0: LoggerInterface, 1: object{entries: array<int, array{message: string, context: array<string, mixed>}>}}
     */
    private function makeRecordingLogger(): array
    {
        // Use a plain object so we get reference semantics without PHP array-reference quirks.
        $holder = new class {
            /** @var array<int, array{message: string, context: array<string, mixed>}> */
            public array $entries = [];
        };

        $logger = new class ($holder) implements LoggerInterface {
            public function __construct(private readonly object $holder) {}

            public function emergency(string|\Stringable $message, array $context = []): void {}
            public function alert(string|\Stringable $message, array $context = []): void {}
            public function critical(string|\Stringable $message, array $context = []): void {}
            public function error(string|\Stringable $message, array $context = []): void {}
            public function warning(string|\Stringable $message, array $context = []): void {}
            public function notice(string|\Stringable $message, array $context = []): void {}

            public function info(string|\Stringable $message, array $context = []): void
            {
                $this->holder->entries[] = ['message' => (string) $message, 'context' => $context];
            }

            public function debug(string|\Stringable $message, array $context = []): void {}

            public function log(\Waaseyaa\Foundation\Log\LogLevel $level, string|\Stringable $message, array $context = []): void
            {
                $this->info($message, $context);
            }
        };

        return [$logger, $holder];
    }

    // -------------------------------------------------------------------------
    // T050-A: Policy declares view_revision → custom rule wins
    // -------------------------------------------------------------------------

    #[Test]
    public function policyDeclaresViewRevisionAllowedCustomRuleWins(): void
    {
        $entity = $this->makeEntity('article');
        $account = $this->makeAccount();
        $revision = $this->makeRevision();

        $policy = new class implements AccessPolicyInterface {
            public function appliesTo(string $entityTypeId): bool { return $entityTypeId === 'article'; }
            public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
            {
                return AccessResult::forbidden('view() should not be called');
            }
            public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
            {
                return AccessResult::neutral();
            }
            public function viewRevision(EntityInterface $entity, AccountInterface $account, RevisionMetadata $revision): AccessResult
            {
                return AccessResult::allowed('explicit revision rule allows');
            }
        };

        $router = new RevisionAccessRouter([$policy]);
        $result = $router->route($entity, $account, $revision);

        $this->assertTrue($result->isAllowed(), 'viewRevision() returned Allowed — router must pass it through.');
    }

    #[Test]
    public function policyDeclaresViewRevisionForbiddenCustomRuleWins(): void
    {
        $entity = $this->makeEntity('article');
        $account = $this->makeAccount();
        $revision = $this->makeRevision();

        $policy = new class implements AccessPolicyInterface {
            public function appliesTo(string $entityTypeId): bool { return $entityTypeId === 'article'; }
            public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
            {
                return AccessResult::allowed('view() should not be called');
            }
            public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
            {
                return AccessResult::neutral();
            }
            public function viewRevision(EntityInterface $entity, AccountInterface $account, RevisionMetadata $revision): AccessResult
            {
                return AccessResult::forbidden('revision is restricted');
            }
        };

        $router = new RevisionAccessRouter([$policy]);
        $result = $router->route($entity, $account, $revision);

        $this->assertTrue($result->isForbidden(), 'viewRevision() returned Forbidden — router must pass it through.');
    }

    // -------------------------------------------------------------------------
    // T050-B: Policy does NOT declare view_revision → fallback to view(), log emitted
    // -------------------------------------------------------------------------

    #[Test]
    public function policyWithoutViewRevisionFallsBackToViewAllowed(): void
    {
        [$logger, $logHolder] = $this->makeRecordingLogger();

        $entity = $this->makeEntity('node');
        $account = $this->makeAccount();
        $revision = $this->makeRevision();

        $policy = new class implements AccessPolicyInterface {
            public function appliesTo(string $entityTypeId): bool { return $entityTypeId === 'node'; }
            public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
            {
                if ($operation === GateInterface::VIEW) {
                    return AccessResult::allowed('node is public');
                }
                return AccessResult::neutral();
            }
            public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
            {
                return AccessResult::neutral();
            }
            // No viewRevision() method declared — fallback path applies.
        };

        $router = new RevisionAccessRouter([$policy], $logger);
        $result = $router->route($entity, $account, $revision);

        $this->assertTrue($result->isAllowed(), 'Fallback to view() which returns Allowed — result must be Allowed.');

        $this->assertCount(1, $logHolder->entries, 'Exactly one log line must be emitted on fallback.');
        $logRecord = $logHolder->entries[0];
        $this->assertSame('view_revision_fallback', $logRecord['context']['outcome'] ?? null);
        $this->assertSame('entity.lifecycle', $logRecord['context']['channel'] ?? null);
        $this->assertSame('node', $logRecord['context']['entity_type_id'] ?? null);
        $this->assertNotEmpty($logRecord['context']['entity_id'] ?? '');
        $this->assertNotEmpty($logRecord['context']['vid'] ?? '');
        $this->assertStringContainsString('AccessPolicyInterface', $logRecord['context']['policy_fqcn'] ?? '');
    }

    #[Test]
    public function logLineContainsExpectedFieldsOnFallback(): void
    {
        [$logger, $logHolder] = $this->makeRecordingLogger();

        $entity = $this->makeEntity('node', 55, 77);
        $account = $this->makeAccount();
        $revision = $this->makeRevision();

        $policy = new class implements AccessPolicyInterface {
            public function appliesTo(string $entityTypeId): bool { return $entityTypeId === 'node'; }
            public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
            {
                return AccessResult::neutral();
            }
            public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
            {
                return AccessResult::neutral();
            }
        };

        $router = new RevisionAccessRouter([$policy], $logger);
        $router->route($entity, $account, $revision);

        $this->assertCount(1, $logHolder->entries);
        $ctx = $logHolder->entries[0]['context'];
        $this->assertSame('55', $ctx['entity_id'], 'entity_id must be cast to string from int 55');
        $this->assertSame('77', $ctx['vid'], 'vid must be cast to string from int 77');
        $this->assertSame('view_revision_fallback', $ctx['outcome']);
        $this->assertSame('entity.lifecycle', $ctx['channel']);
    }

    // -------------------------------------------------------------------------
    // T050-C: Anonymous account + non-public entity → fallback respects view() result
    // -------------------------------------------------------------------------

    #[Test]
    public function anonymousAccountFallbackReturnsForbiddenWhenViewForbids(): void
    {
        [$logger, $logHolder] = $this->makeRecordingLogger();

        $entity = $this->makeEntity('node');
        $account = $this->makeAccount(authenticated: false); // anonymous
        $revision = $this->makeRevision();

        $policy = new class implements AccessPolicyInterface {
            public function appliesTo(string $entityTypeId): bool { return $entityTypeId === 'node'; }
            public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
            {
                if ($operation === GateInterface::VIEW && !$account->isAuthenticated()) {
                    return AccessResult::forbidden('anonymous cannot view');
                }
                return AccessResult::neutral();
            }
            public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
            {
                return AccessResult::neutral();
            }
        };

        $router = new RevisionAccessRouter([$policy], $logger);
        $result = $router->route($entity, $account, $revision);

        $this->assertTrue($result->isForbidden(), 'view() returns Forbidden for anon — fallback must not widen access to Allowed.');
        $this->assertCount(1, $logHolder->entries, 'Fallback log line must be emitted.');
    }

    #[Test]
    public function anonymousAccountFallbackReturnsAllowedWhenViewAllows(): void
    {
        $entity = $this->makeEntity('page');
        $account = $this->makeAccount(authenticated: false);
        $revision = $this->makeRevision();

        $policy = new class implements AccessPolicyInterface {
            public function appliesTo(string $entityTypeId): bool { return $entityTypeId === 'page'; }
            public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
            {
                return AccessResult::allowed('public page');
            }
            public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
            {
                return AccessResult::neutral();
            }
        };

        $router = new RevisionAccessRouter([$policy]);
        $result = $router->route($entity, $account, $revision);

        $this->assertTrue($result->isAllowed(), 'view() returns Allowed even for anon — fallback must return Allowed.');
    }

    // -------------------------------------------------------------------------
    // T050-D: Default-deny regression — absence of view_revision MUST NOT deny
    // -------------------------------------------------------------------------

    #[Test]
    public function defaultDenyRegression_absenceOfViewRevisionDoesNotDenyWhenViewAllows(): void
    {
        // This is the critical regression guard: a legacy policy that ONLY implements
        // view() and never heard of view_revision must still get ALLOWED when view() says so.
        $entity = $this->makeEntity('legacy_content');
        $account = $this->makeAccount();
        $revision = $this->makeRevision();

        // This policy has no viewRevision() method — it is a "legacy" policy.
        $legacyPolicy = new class implements AccessPolicyInterface {
            public function appliesTo(string $entityTypeId): bool { return $entityTypeId === 'legacy_content'; }
            public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
            {
                // Any authenticated user may view.
                if ($operation === GateInterface::VIEW && $account->isAuthenticated()) {
                    return AccessResult::allowed('authenticated user may view');
                }
                return AccessResult::forbidden('not authenticated');
            }
            public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
            {
                return AccessResult::neutral();
            }
        };

        $router = new RevisionAccessRouter([$legacyPolicy]);
        $result = $router->route($entity, $account, $revision);

        $this->assertTrue(
            $result->isAllowed(),
            'REGRESSION: A legacy policy without viewRevision() but with view() returning Allowed '
            . 'MUST yield Allowed on view_revision. Default-deny on missing viewRevision is a contract violation.',
        );
    }

    #[Test]
    public function noPolicyAtAllReturnsNeutralNotForbidden(): void
    {
        // No policies registered at all — must not default-deny.
        $entity = $this->makeEntity('orphan');
        $account = $this->makeAccount();
        $revision = $this->makeRevision();

        $router = new RevisionAccessRouter([]);
        $result = $router->route($entity, $account, $revision);

        $this->assertFalse(
            $result->isForbidden(),
            'With no registered policies, result must not be Forbidden. Got: ' . $result->status->name,
        );
    }

    // -------------------------------------------------------------------------
    // T048: PolicyAttribute::validate() boot-time failure when op declared but method missing
    // -------------------------------------------------------------------------

    #[Test]
    public function policyAttributeValidateThrowsWhenViewRevisionDeclaredButMethodMissing(): void
    {
        // Simulates a policy class that declares 'view_revision' in operations
        // but is missing the viewRevision() method — must throw at boot time.
        $missingMethodClass = new class implements AccessPolicyInterface {
            public function appliesTo(string $entityTypeId): bool { return true; }
            public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
            {
                return AccessResult::neutral();
            }
            public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
            {
                return AccessResult::neutral();
            }
            // Intentionally NO viewRevision() method.
        };

        $attribute = new PolicyAttribute(
            entityType: 'node',
            operations: [GateInterface::VIEW_REVISION],
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/viewRevision/');
        $this->expectExceptionMessageMatches('/view_revision/');

        $attribute->validate($missingMethodClass::class);
    }

    #[Test]
    public function policyAttributeValidatePassesWhenViewRevisionMethodPresent(): void
    {
        $validPolicyClass = new class implements AccessPolicyInterface {
            public function appliesTo(string $entityTypeId): bool { return true; }
            public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
            {
                return AccessResult::neutral();
            }
            public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
            {
                return AccessResult::neutral();
            }
            public function viewRevision(EntityInterface $entity, AccountInterface $account, RevisionMetadata $revision): AccessResult
            {
                return AccessResult::allowed();
            }
        };

        $attribute = new PolicyAttribute(
            entityType: 'node',
            operations: [GateInterface::VIEW_REVISION],
        );

        // Must NOT throw.
        $attribute->validate($validPolicyClass::class);
        $this->assertTrue(true, 'validate() passed without exception for a correctly implemented policy.');
    }

    #[Test]
    public function policyAttributeValidatePassesWhenNoOperationsDeclared(): void
    {
        // Default (no operations) — validate() is a no-op.
        $anyPolicyClass = new class implements AccessPolicyInterface {
            public function appliesTo(string $entityTypeId): bool { return true; }
            public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
            {
                return AccessResult::neutral();
            }
            public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
            {
                return AccessResult::neutral();
            }
        };

        $attribute = new PolicyAttribute(entityType: 'node');
        $attribute->validate($anyPolicyClass::class);
        $this->assertTrue(true, 'No operations declared — validate() must be silent.');
    }
}
