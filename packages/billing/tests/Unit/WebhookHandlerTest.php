<?php

declare(strict_types=1);

namespace Waaseyaa\Billing\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Billing\FakeStripeClient;
use Waaseyaa\Billing\WebhookHandler;

/**
 * @covers \Waaseyaa\Billing\WebhookHandler
 */
#[CoversClass(WebhookHandler::class)]
final class WebhookHandlerTest extends TestCase
{
    private FakeStripeClient $stripe;
    private WebhookHandler $handler;

    protected function setUp(): void
    {
        $this->stripe = new FakeStripeClient();
        $this->handler = new WebhookHandler($this->stripe);
    }

    public function testHandleCheckoutSessionCompleted(): void
    {
        $this->stripe->setNextWebhookEvent([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'customer' => 'cus_abc',
                    'subscription' => 'sub_123',
                    'metadata' => ['user_id' => 'user-1'],
                ],
            ],
        ]);

        $result = $this->handler->handle('payload', 'sig');

        $this->assertSame('checkout.session.completed', $result['event']);
        $this->assertSame('cus_abc', $result['customer_id']);
        $this->assertSame('sub_123', $result['subscription_id']);
    }

    public function testHandleSubscriptionCreated(): void
    {
        $this->stripe->setNextWebhookEvent([
            'type' => 'customer.subscription.created',
            'data' => [
                'object' => [
                    'id' => 'sub_123',
                    'customer' => 'cus_abc',
                    'status' => 'active',
                    'items' => [
                        'data' => [
                            ['price' => ['id' => 'price_pro_monthly']],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->handler->handle('payload', 'sig');

        $this->assertSame('customer.subscription.created', $result['event']);
        $this->assertSame('sub_123', $result['subscription_id']);
        $this->assertSame('active', $result['status']);
        $this->assertSame('price_pro_monthly', $result['price_id']);
    }

    public function testHandleSubscriptionUpdated(): void
    {
        $this->stripe->setNextWebhookEvent([
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_123',
                    'customer' => 'cus_abc',
                    'status' => 'past_due',
                    'items' => [
                        'data' => [
                            ['price' => ['id' => 'price_pro_monthly']],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->handler->handle('payload', 'sig');

        $this->assertSame('customer.subscription.updated', $result['event']);
        $this->assertSame('past_due', $result['status']);
    }

    public function testHandleSubscriptionDeleted(): void
    {
        $this->stripe->setNextWebhookEvent([
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'id' => 'sub_123',
                    'customer' => 'cus_abc',
                    'status' => 'canceled',
                    'items' => [
                        'data' => [
                            ['price' => ['id' => 'price_pro_monthly']],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->handler->handle('payload', 'sig');

        $this->assertSame('customer.subscription.deleted', $result['event']);
        $this->assertSame('canceled', $result['status']);
    }

    public function testHandleInvoicePaymentSucceeded(): void
    {
        $this->stripe->setNextWebhookEvent([
            'type' => 'invoice.payment_succeeded',
            'data' => [
                'object' => [
                    'customer' => 'cus_abc',
                    'subscription' => 'sub_123',
                    'amount_paid' => 1999,
                ],
            ],
        ]);

        $result = $this->handler->handle('payload', 'sig');

        $this->assertSame('invoice.payment_succeeded', $result['event']);
        $this->assertSame(1999, $result['amount_paid']);
    }

    public function testHandleInvoicePaymentFailed(): void
    {
        $this->stripe->setNextWebhookEvent([
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'customer' => 'cus_abc',
                    'subscription' => 'sub_123',
                    'amount_due' => 1999,
                ],
            ],
        ]);

        $result = $this->handler->handle('payload', 'sig');

        $this->assertSame('invoice.payment_failed', $result['event']);
        $this->assertSame(1999, $result['amount_due']);
    }

    public function testHandleUnknownEventReturnsNull(): void
    {
        $this->stripe->setNextWebhookEvent([
            'type' => 'charge.succeeded',
            'data' => ['object' => []],
        ]);

        $result = $this->handler->handle('payload', 'sig');

        $this->assertNull($result);
    }
}
