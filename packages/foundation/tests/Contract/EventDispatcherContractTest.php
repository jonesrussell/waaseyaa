<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Waaseyaa\Foundation\Event\EventDispatcherInterface;
use Waaseyaa\Foundation\Event\SymfonyEventDispatcherAdapter;

/**
 * Contract test for the Waaseyaa event dispatcher.
 *
 * Verifies the four behaviors WP03 spec requires:
 *
 * 1. dispatch returns the same event instance (PSR-14)
 * 2. listener registration + invocation
 * 3. subscriber registration (Symfony-style)
 * 4. stoppable-event semantics (PSR-14)
 *
 * The test runs against the default `SymfonyEventDispatcherAdapter` binding;
 * any future implementation of `EventDispatcherInterface` should satisfy the
 * same expectations.
 */
#[CoversNothing]
final class EventDispatcherContractTest extends TestCase
{
    private function dispatcher(): EventDispatcherInterface
    {
        return new SymfonyEventDispatcherAdapter();
    }

    #[Test]
    public function dispatch_returns_the_same_event_instance(): void
    {
        $dispatcher = $this->dispatcher();
        $event = new \stdClass();

        $returned = $dispatcher->dispatch($event);

        $this->assertSame($event, $returned);
    }

    #[Test]
    public function listener_registration_invokes_the_callable(): void
    {
        $dispatcher = $this->dispatcher();
        $captured = [];

        $dispatcher->addListener('test.named', static function (object $event) use (&$captured): void {
            $captured[] = $event;
        });

        $event = new \stdClass();
        $dispatcher->dispatch($event, 'test.named');

        $this->assertCount(1, $captured);
        $this->assertSame($event, $captured[0]);
    }

    #[Test]
    public function listener_priority_orders_higher_first(): void
    {
        $dispatcher = $this->dispatcher();
        $order = [];

        $dispatcher->addListener('priority.test', static function () use (&$order): void {
            $order[] = 'low';
        }, -10);
        $dispatcher->addListener('priority.test', static function () use (&$order): void {
            $order[] = 'high';
        }, 10);
        $dispatcher->addListener('priority.test', static function () use (&$order): void {
            $order[] = 'mid';
        }, 0);

        $dispatcher->dispatch(new \stdClass(), 'priority.test');

        $this->assertSame(['high', 'mid', 'low'], $order);
    }

    #[Test]
    public function subscriber_registration_follows_symfony_discovery(): void
    {
        $dispatcher = $this->dispatcher();
        $captured = [];

        $subscriber = new class ($captured) implements EventSubscriberInterface {
            /** @var array<int, string> */
            public function __construct(private array &$captured) {}

            public static function getSubscribedEvents(): array
            {
                return [
                    'subscribed.event' => 'onEvent',
                ];
            }

            public function onEvent(object $event, string $name): void
            {
                $this->captured[] = $name;
            }
        };

        $dispatcher->addSubscriber($subscriber);
        $dispatcher->dispatch(new \stdClass(), 'subscribed.event');

        $this->assertSame(['subscribed.event'], $captured);
    }

    #[Test]
    public function stoppable_event_halts_subsequent_listeners(): void
    {
        $dispatcher = $this->dispatcher();
        $invocations = [];

        $dispatcher->addListener('stoppable.test', static function (Event $event) use (&$invocations): void {
            $invocations[] = 'first';
            $event->stopPropagation();
        }, 100);
        $dispatcher->addListener('stoppable.test', static function () use (&$invocations): void {
            $invocations[] = 'second';
        }, 0);

        $dispatcher->dispatch(new Event(), 'stoppable.test');

        $this->assertSame(['first'], $invocations);
    }

    #[Test]
    public function remove_listener_prevents_further_invocations(): void
    {
        $dispatcher = $this->dispatcher();
        $count = 0;
        $listener = static function () use (&$count): void {
            $count++;
        };

        $dispatcher->addListener('removable.test', $listener);
        $dispatcher->dispatch(new \stdClass(), 'removable.test');
        $this->assertSame(1, $count);

        $dispatcher->removeListener('removable.test', $listener);
        $dispatcher->dispatch(new \stdClass(), 'removable.test');
        $this->assertSame(1, $count, 'Listener must not fire after removal');
    }
}
