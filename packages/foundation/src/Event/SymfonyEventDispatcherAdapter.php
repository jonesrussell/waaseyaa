<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Event;

use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as SymfonyComponentEventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Default implementation of `EventDispatcherInterface` backed by Symfony's
 * `\Symfony\Component\EventDispatcher\EventDispatcher`.
 *
 * Per C-003 the adapter is the default kernel binding for the Waaseyaa
 * dispatcher contract. It also implements Symfony's component
 * `EventDispatcherInterface` (which extends PSR-14 and the Symfony contract
 * interface) so existing framework-internal call sites still typed against
 * Symfony — the kernel bootstrap, the CLI command registry, and the
 * `EventListCommand` discovery — continue to accept the same instance. The
 * abstraction is added without forcing a foundation-wide type-hint sweep in
 * this WP.
 */
final class SymfonyEventDispatcherAdapter implements
    EventDispatcherInterface,
    SymfonyComponentEventDispatcherInterface
{
    public function __construct(
        private readonly SymfonyEventDispatcher $inner = new SymfonyEventDispatcher(),
    ) {}

    public function dispatch(object $event, ?string $eventName = null): object
    {
        return $this->inner->dispatch($event, $eventName);
    }

    public function addListener(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->inner->addListener($eventName, $listener, $priority);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->inner->addSubscriber($subscriber);
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        $this->inner->removeListener($eventName, $listener);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->inner->removeSubscriber($subscriber);
    }

    public function getListeners(?string $eventName = null): array
    {
        return $this->inner->getListeners($eventName);
    }

    public function getListenerPriority(string $eventName, callable $listener): ?int
    {
        return $this->inner->getListenerPriority($eventName, $listener);
    }

    public function hasListeners(?string $eventName = null): bool
    {
        return $this->inner->hasListeners($eventName);
    }
}
