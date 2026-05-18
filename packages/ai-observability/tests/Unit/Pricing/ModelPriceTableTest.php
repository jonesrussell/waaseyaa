<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Tests\Unit\Pricing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Observability\Pricing\ModelPriceTable;

#[CoversClass(ModelPriceTable::class)]
final class ModelPriceTableTest extends TestCase
{
    #[Test]
    public function knownAnthropicSonnetCostMatchesExpectedIntegerArithmetic(): void
    {
        $table = new ModelPriceTable();

        // 1_000_000 input tokens at 300 cents/M = 300 cents.
        // 200_000 output tokens at 1500 cents/M = 300 cents.
        // Total = 600 cents.
        $cost = $table->priceCentsFor('anthropic:claude-sonnet-4-6', 1_000_000, 200_000);

        self::assertSame(600, $cost);
    }

    #[Test]
    public function knownAnthropicOpusCostMatchesExpectedIntegerArithmetic(): void
    {
        $table = new ModelPriceTable();

        // 2_000_000 input at 1500 c/M = 3000.
        // 500_000 output at 7500 c/M = 3750.
        $cost = $table->priceCentsFor('anthropic:claude-opus-4-7', 2_000_000, 500_000);

        self::assertSame(6750, $cost);
    }

    #[Test]
    public function knownOpenAiModelReturnsIntegerCost(): void
    {
        $table = new ModelPriceTable();
        $cost = $table->priceCentsFor('openai:gpt-4o', 4_000_000, 1_000_000);

        // 4_000_000 * 250 / 1_000_000 = 1000.
        // 1_000_000 * 1000 / 1_000_000 = 1000.
        self::assertSame(2000, $cost);
    }

    #[Test]
    public function nullProviderIsZeroCostSentinel(): void
    {
        $table = new ModelPriceTable();

        self::assertSame(0, $table->priceCentsFor('null:null', 1_000_000, 1_000_000));
    }

    #[Test]
    public function unknownModelReturnsNull(): void
    {
        $table = new ModelPriceTable();

        self::assertNull($table->priceCentsFor('unknown:no-such-model', 1000, 1000));
        self::assertNull($table->priceCentsFor('', 1000, 1000));
    }

    #[Test]
    public function negativeTokensReturnNull(): void
    {
        $table = new ModelPriceTable();

        self::assertNull($table->priceCentsFor('anthropic:claude-sonnet-4-6', -1, 0));
        self::assertNull($table->priceCentsFor('anthropic:claude-sonnet-4-6', 0, -1));
    }

    #[Test]
    public function smallTokenCountsFloorToZeroWithoutFloatDrift(): void
    {
        $table = new ModelPriceTable();

        // 100 tokens × 300 cents/M = 30_000 / 1_000_000 = 0 (floor).
        self::assertSame(0, $table->priceCentsFor('anthropic:claude-sonnet-4-6', 100, 0));

        // Arithmetic must be repeatable — no float rounding noise.
        for ($i = 0; $i < 10; $i++) {
            self::assertSame(
                0,
                $table->priceCentsFor('anthropic:claude-sonnet-4-6', 100, 0),
            );
        }
    }

    #[Test]
    public function knownModelsListsCanonicalKeys(): void
    {
        $table = new ModelPriceTable();
        $models = $table->knownModels();

        self::assertContains('anthropic:claude-sonnet-4-6', $models);
        self::assertContains('anthropic:claude-opus-4-7', $models);
        self::assertContains('openai:gpt-4o', $models);
        self::assertContains('null:null', $models);
    }
}
