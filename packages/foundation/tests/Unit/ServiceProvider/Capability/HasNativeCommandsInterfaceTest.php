<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\ServiceProvider\Capability;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;

#[CoversClass(HasNativeCommandsInterface::class)]
final class HasNativeCommandsInterfaceTest extends TestCase
{
    #[Test]
    public function implementorCanReturnEmptyIterable(): void
    {
        $provider = new class implements HasNativeCommandsInterface {
            public function nativeCommands(): iterable
            {
                return [];
            }
        };

        $commands = iterator_to_array($provider->nativeCommands());
        self::assertSame([], $commands);
    }

    #[Test]
    public function implementorCanYieldCommandDefinitions(): void
    {
        // Use stdClass as a stand-in — the interface only requires iterable,
        // the actual type check is enforced by CliKernelServiceProvider at runtime.
        $fake = new \stdClass();

        $provider = new class ($fake) implements HasNativeCommandsInterface {
            public function __construct(private readonly \stdClass $cmd) {}

            public function nativeCommands(): iterable
            {
                yield $this->cmd;
            }
        };

        $commands = iterator_to_array($provider->nativeCommands());
        self::assertCount(1, $commands);
        self::assertSame($fake, $commands[0]);
    }

    #[Test]
    public function implementorCanUseGenerator(): void
    {
        $provider = new class implements HasNativeCommandsInterface {
            public function nativeCommands(): iterable
            {
                yield new \stdClass();
                yield new \stdClass();
            }
        };

        $commands = iterator_to_array($provider->nativeCommands());
        self::assertCount(2, $commands);
    }

    #[Test]
    public function nativeCommandsIsIdempotent(): void
    {
        $provider = new class implements HasNativeCommandsInterface {
            public function nativeCommands(): iterable
            {
                return [new \stdClass()];
            }
        };

        $first = iterator_to_array($provider->nativeCommands());
        $second = iterator_to_array($provider->nativeCommands());

        self::assertCount(1, $first);
        self::assertCount(1, $second);
    }

    #[Test]
    public function interfaceIsInFoundationCapabilityNamespace(): void
    {
        $ref = new \ReflectionClass(HasNativeCommandsInterface::class);
        self::assertSame(
            'Waaseyaa\Foundation\ServiceProvider\Capability',
            $ref->getNamespaceName(),
        );
    }

    #[Test]
    public function interfaceDeclaresNativeCommandsMethod(): void
    {
        $ref = new \ReflectionClass(HasNativeCommandsInterface::class);
        self::assertTrue($ref->hasMethod('nativeCommands'));

        $method = $ref->getMethod('nativeCommands');
        self::assertTrue($method->isPublic());
        self::assertSame('iterable', (string) $method->getReturnType());
    }
}
