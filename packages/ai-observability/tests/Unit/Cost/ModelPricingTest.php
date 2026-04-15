<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Tests\Unit\Cost;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Observability\Cost\ModelPricing;

#[CoversClass(ModelPricing::class)]
final class ModelPricingTest extends TestCase
{
    #[Test]
    public function defaultsIncludeClaudeOpus(): void
    {
        $pricing = new ModelPricing();
        $rates = $pricing->forModel('claude-opus-4-6');

        self::assertGreaterThan(0.0, $rates['input']);
        self::assertGreaterThan(0.0, $rates['output']);
    }

    #[Test]
    public function unknownModelReturnsZeroRates(): void
    {
        $pricing = new ModelPricing();
        $rates = $pricing->forModel('nonexistent-xyz');

        self::assertSame(0.0, $rates['input']);
        self::assertSame(0.0, $rates['output']);
        self::assertSame(0.0, $rates['cached']);
    }

    #[Test]
    public function customPricingOverridesDefaults(): void
    {
        $pricing = new ModelPricing(['my-model' => ['input' => 2.0, 'output' => 8.0, 'cached' => 0.2]]);
        $rates = $pricing->forModel('my-model');

        self::assertSame(2.0, $rates['input']);
    }
}
