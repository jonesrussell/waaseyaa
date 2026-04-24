<?php

declare(strict_types=1);

namespace Waaseyaa\Billing\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Billing\PlanTier;

/**
 * @covers \Waaseyaa\Billing\PlanTier
 */
#[CoversClass(PlanTier::class)]
final class PlanTierTest extends TestCase
{
    public function testAllTiersExist(): void
    {
        $this->assertSame('free', PlanTier::Free->value);
        $this->assertSame('pro', PlanTier::Pro->value);
        $this->assertSame('business', PlanTier::Business->value);
        $this->assertSame('growth', PlanTier::Growth->value);
        $this->assertSame('enterprise', PlanTier::Enterprise->value);
    }

    public function testFromValidString(): void
    {
        $this->assertSame(PlanTier::Pro, PlanTier::fromString('pro'));
        $this->assertSame(PlanTier::Business, PlanTier::fromString('business'));
    }

    public function testFromInvalidStringReturnsFree(): void
    {
        $this->assertSame(PlanTier::Free, PlanTier::fromString('invalid'));
        $this->assertSame(PlanTier::Free, PlanTier::fromString(''));
    }

    public function testFoundingMapsToBusiness(): void
    {
        $this->assertSame(PlanTier::Business, PlanTier::fromString('founding'));
    }

    public function testIsValidReturnsTrueForValidTiers(): void
    {
        $this->assertTrue(PlanTier::isValid('free'));
        $this->assertTrue(PlanTier::isValid('pro'));
        $this->assertTrue(PlanTier::isValid('business'));
        $this->assertTrue(PlanTier::isValid('growth'));
        $this->assertTrue(PlanTier::isValid('enterprise'));
    }

    public function testIsValidReturnsFalseForInvalidTiers(): void
    {
        $this->assertFalse(PlanTier::isValid('invalid'));
        $this->assertFalse(PlanTier::isValid('founding'));
        $this->assertFalse(PlanTier::isValid(''));
    }

    public function testIsPaidReturnsTrueForPaidTiers(): void
    {
        $this->assertTrue(PlanTier::Pro->isPaid());
        $this->assertTrue(PlanTier::Business->isPaid());
        $this->assertTrue(PlanTier::Growth->isPaid());
        $this->assertTrue(PlanTier::Enterprise->isPaid());
    }

    public function testIsPaidReturnsFalseForFree(): void
    {
        $this->assertFalse(PlanTier::Free->isPaid());
    }
}
