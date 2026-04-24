<?php

declare(strict_types=1);

namespace Waaseyaa\Billing\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Billing\CheckoutSession;

/**
 * @covers \Waaseyaa\Billing\CheckoutSession
 */
#[CoversClass(CheckoutSession::class)]
final class CheckoutSessionTest extends TestCase
{
    public function testConstructAndAccessors(): void
    {
        $session = new CheckoutSession(
            id: 'cs_test_123',
            url: 'https://checkout.stripe.com/cs_test_123',
        );

        $this->assertSame('cs_test_123', $session->id);
        $this->assertSame('https://checkout.stripe.com/cs_test_123', $session->url);
    }
}
