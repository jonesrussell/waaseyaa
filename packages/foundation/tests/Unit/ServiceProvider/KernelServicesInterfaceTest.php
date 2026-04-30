<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\ServiceProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\ServiceProvider\KernelServicesInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

#[CoversClass(ServiceProvider::class)]
final class KernelServicesInterfaceTest extends TestCase
{
    #[Test]
    public function service_provider_uses_kernel_services_get_for_unbound_abstract(): void
    {
        $kernelOnly = new \stdClass();
        $kernelOnly->origin = 'kernel-services';

        $services = new class ($kernelOnly) implements KernelServicesInterface {
            public function __construct(private readonly \stdClass $kernelOnly) {}

            public function get(string $abstract): ?object
            {
                return $abstract === \stdClass::class ? $this->kernelOnly : null;
            }
        };

        $provider = new class extends ServiceProvider {
            public function register(): void {}
        };
        $provider->setKernelServices($services);

        $this->assertSame($kernelOnly, $provider->resolve(\stdClass::class));
    }

    #[Test]
    public function local_binding_takes_precedence_over_kernel_services(): void
    {
        $kernelInstance = new \stdClass();
        $localInstance  = new \stdClass();

        $services = new class ($kernelInstance) implements KernelServicesInterface {
            public function __construct(private readonly \stdClass $kernelInstance) {}

            public function get(string $abstract): ?object
            {
                return $this->kernelInstance;
            }
        };

        $provider = new class ($localInstance) extends ServiceProvider {
            public function __construct(private readonly \stdClass $localInstance) {}

            public function register(): void
            {
                $this->singleton(\stdClass::class, fn (): \stdClass => $this->localInstance);
            }
        };
        $provider->register();
        $provider->setKernelServices($services);

        $this->assertSame($localInstance, $provider->resolve(\stdClass::class));
    }

    #[Test]
    public function kernel_services_null_return_falls_through_to_runtime_exception(): void
    {
        $services = new class implements KernelServicesInterface {
            public function get(string $abstract): ?object
            {
                return null;
            }
        };

        $provider = new class extends ServiceProvider {
            public function register(): void {}
        };
        $provider->setKernelServices($services);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No binding registered for Unknown\\Abstract');
        $provider->resolve('Unknown\\Abstract');
    }

    #[Test]
    public function kernel_services_propagates_to_merged_child_provider(): void
    {
        $shared = new \stdClass();

        $services = new class ($shared) implements KernelServicesInterface {
            public function __construct(private readonly \stdClass $shared) {}

            public function get(string $abstract): ?object
            {
                return $abstract === \stdClass::class ? $this->shared : null;
            }
        };

        $child = new class extends ServiceProvider {
            public function register(): void {}
        };

        $parent = new class ($child) extends ServiceProvider {
            public function __construct(private readonly ServiceProvider $child) {}

            public function register(): void
            {
                $this->mergeChildProvider($this->child);
            }
        };
        $parent->setKernelServices($services);
        $parent->register();

        // Child receives parent's KernelServices via mergeChildProvider.
        $this->assertSame($shared, $child->resolve(\stdClass::class));
    }
}
