<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Config\Dependency\ConfigDependencyInterface;

/**
 * Contract test for {@see ConfigDependencyInterface}.
 *
 * Verifies the stable-surface commitments declared in
 * `kitty-specs/config-management-v1-01KRCDEC/contracts/dependency-graph.md`:
 *  - The interface FQCN.
 *  - The method name `configDependencies()`.
 *  - The return type `list<string>` (PHP signature: `array`).
 *  - A no-op default implementation that returns `[]` is wire-compatible.
 *
 * Marked `@CoversNothing` because contract tests document API shape, not a
 * single implementation. Concrete tests of `DependencyResolver` and
 * `DependencyGraph` exercise the behavior side.
 */
#[CoversNothing]
final class ConfigDependencyInterfaceContractTest extends TestCase
{
    #[Test]
    public function interfaceFqcnIsStable(): void
    {
        self::assertTrue(\interface_exists(ConfigDependencyInterface::class));
        self::assertSame(
            'Waaseyaa\\Config\\Dependency\\ConfigDependencyInterface',
            ConfigDependencyInterface::class,
        );
    }

    #[Test]
    public function configDependenciesMethodExistsWithExpectedSignature(): void
    {
        $reflection = new \ReflectionClass(ConfigDependencyInterface::class);
        self::assertTrue($reflection->hasMethod('configDependencies'));

        $method = $reflection->getMethod('configDependencies');
        self::assertTrue($method->isPublic(), 'configDependencies() must be public.');
        self::assertSame(0, $method->getNumberOfParameters(), 'configDependencies() takes no parameters.');

        $returnType = $method->getReturnType();
        self::assertInstanceOf(\ReflectionNamedType::class, $returnType);
        self::assertSame('array', $returnType->getName());
    }

    #[Test]
    public function defaultNoOpImplementationReturnsEmptyList(): void
    {
        $entity = new class () implements ConfigDependencyInterface {
            public function configDependencies(): array
            {
                return [];
            }
        };

        self::assertSame([], $entity->configDependencies());
    }

    #[Test]
    public function implementationsMayDeclareDependencies(): void
    {
        $entity = new class () implements ConfigDependencyInterface {
            public function configDependencies(): array
            {
                return ['role.admin', 'taxonomy_vocabulary.categories'];
            }
        };

        $declared = $entity->configDependencies();
        self::assertCount(2, $declared);
        self::assertContains('role.admin', $declared);
        self::assertContains('taxonomy_vocabulary.categories', $declared);

        // Each entry must match the documented ref pattern.
        foreach ($declared as $ref) {
            self::assertMatchesRegularExpression(
                '/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/',
                $ref,
                'configDependencies() entries must match <entity_type>.<entity_id>.',
            );
        }
    }
}
