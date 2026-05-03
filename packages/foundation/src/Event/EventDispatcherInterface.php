<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Event;

use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Waaseyaa-owned event dispatcher contract.
 *
 * Per ratified contract C-003 of mission 1107-api-symfony-decoupling, app code
 * type-hints this interface instead of Symfony's
 * \Symfony\Contracts\EventDispatcher\EventDispatcherInterface or
 * \Symfony\Component\EventDispatcher\EventDispatcherInterface.
 *
 * Conformance:
 *
 * - **PSR-14 dispatch.** Extends \Psr\EventDispatcher\EventDispatcherInterface
 *   so any PSR-14 caller works. The optional `$eventName` second parameter is
 *   a Symfony-compatible extension that PSR-14 callers may ignore — passing
 *   only the event object is the canonical PSR-14 path.
 * - **Stoppable events.** Implementers must respect
 *   \Psr\EventDispatcher\StoppableEventInterface; once `isPropagationStopped()`
 *   returns true no further listeners run.
 * - **Symfony-style listener and subscriber registration.** The methods below
 *   intentionally mirror Symfony's component interface so existing
 *   `EventSubscriberInterface` implementations and listener-priority idioms
 *   continue to work unchanged. C-003 keeps `DomainEvent extends Symfony Event`
 *   for the same reason: subscriber and event semantics stay Symfony-typed,
 *   only the dispatcher gets a Waaseyaa-owned name.
 *
 * The kernel binds `SymfonyEventDispatcherAdapter` as the default
 * implementation; consumers can replace the binding with any other
 * implementation that satisfies this contract.
 */
interface EventDispatcherInterface extends PsrEventDispatcherInterface
{
    /**
     * Dispatch an event to all registered listeners.
     *
     * The optional `$eventName` parameter overrides the default event name
     * (the event class FQCN). PSR-14 callers may omit it.
     */
    public function dispatch(object $event, ?string $eventName = null): object;

    /**
     * Register a listener for the named event.
     *
     * Higher priority runs first; ties run in registration order.
     */
    public function addListener(string $eventName, callable $listener, int $priority = 0): void;

    /**
     * Register a Symfony-style subscriber.
     *
     * The subscriber's `getSubscribedEvents()` map drives listener
     * registration; subscriber-discovery semantics match Symfony's.
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): void;

    /**
     * Remove a previously-registered listener.
     */
    public function removeListener(string $eventName, callable $listener): void;

    /**
     * Remove a previously-registered subscriber.
     */
    public function removeSubscriber(EventSubscriberInterface $subscriber): void;

    /**
     * Return registered listeners.
     *
     * @return array<int, array<int, callable>|callable>
     */
    public function getListeners(?string $eventName = null): array;
}
