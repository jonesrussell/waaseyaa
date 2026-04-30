<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Observability\Analysis\AnomalyDetector;
use Waaseyaa\AI\Observability\Cost\ModelPricing;
use Waaseyaa\AI\Observability\ObservabilityServiceProvider;
use Waaseyaa\AI\Observability\Recorder\NullTraceRecorder;
use Waaseyaa\AI\Observability\Recorder\TraceRecorderInterface;
use Waaseyaa\AI\Observability\TraceContext;

#[CoversClass(ObservabilityServiceProvider::class)]
final class ObservabilityServiceProviderTest extends TestCase
{
    #[Test]
    public function disabledModeBindsNullTraceRecorder(): void
    {
        $provider = new ObservabilityServiceProvider();
        $provider->setKernelContext('', ['observability' => ['enabled' => false]], []);
        $provider->register();

        $recorder = $provider->resolve(TraceRecorderInterface::class);

        self::assertInstanceOf(NullTraceRecorder::class, $recorder);
    }

    #[Test]
    public function registersLocalBindingsWithoutKernel(): void
    {
        $provider = new ObservabilityServiceProvider();
        $provider->setKernelContext('', [], []);
        $provider->register();

        self::assertInstanceOf(TraceContext::class, $provider->resolve(TraceContext::class));
        self::assertInstanceOf(ModelPricing::class, $provider->resolve(ModelPricing::class));
        self::assertInstanceOf(AnomalyDetector::class, $provider->resolve(AnomalyDetector::class));
    }

    #[Test]
    public function registersTraceEntityType(): void
    {
        $provider = new ObservabilityServiceProvider();
        $provider->setKernelContext('', [], []);
        $provider->register();

        $entityTypes = $provider->getEntityTypes();
        self::assertCount(1, $entityTypes);
        self::assertSame('trace', $entityTypes[0]->id());
    }
}
