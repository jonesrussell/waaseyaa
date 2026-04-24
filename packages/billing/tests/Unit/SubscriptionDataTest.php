<?php

declare(strict_types=1);

namespace Waaseyaa\Billing\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Billing\SubscriptionData;

/**
 * @covers \Waaseyaa\Billing\SubscriptionData
 */
#[CoversClass(SubscriptionData::class)]
final class SubscriptionDataTest extends TestCase
{
    public function testConstructAndAccessors(): void
    {
        $sub = new SubscriptionData(
            stripeId: 'sub_123',
            stripeStatus: 'active',
            stripePrice: 'price_pro_monthly',
            quantity: 1,
            trialEndsAt: null,
            endsAt: null,
        );

        $this->assertSame('sub_123', $sub->stripeId);
        $this->assertSame('active', $sub->stripeStatus);
        $this->assertSame('price_pro_monthly', $sub->stripePrice);
        $this->assertSame(1, $sub->quantity);
        $this->assertNull($sub->trialEndsAt);
        $this->assertNull($sub->endsAt);
    }

    public function testIsActive(): void
    {
        $active = new SubscriptionData('sub_1', 'active', 'price_1', 1, null, null);
        $trialing = new SubscriptionData('sub_2', 'trialing', 'price_1', 1, null, null);
        $canceled = new SubscriptionData('sub_3', 'canceled', 'price_1', 1, null, null);
        $pastDue = new SubscriptionData('sub_4', 'past_due', 'price_1', 1, null, null);

        $this->assertTrue($active->isActive());
        $this->assertTrue($trialing->isActive());
        $this->assertFalse($canceled->isActive());
        $this->assertFalse($pastDue->isActive());
    }

    public function testHasPrice(): void
    {
        $sub = new SubscriptionData('sub_1', 'active', 'price_pro_monthly', 1, null, null);

        $this->assertTrue($sub->hasPrice('price_pro_monthly'));
        $this->assertFalse($sub->hasPrice('price_growth_monthly'));
    }

    public function testHasAnyPrice(): void
    {
        $sub = new SubscriptionData('sub_1', 'active', 'price_pro_monthly', 1, null, null);

        $this->assertTrue($sub->hasAnyPrice(['price_pro_monthly', 'price_pro_yearly']));
        $this->assertFalse($sub->hasAnyPrice(['price_growth_monthly', 'price_growth_yearly']));
    }
}
