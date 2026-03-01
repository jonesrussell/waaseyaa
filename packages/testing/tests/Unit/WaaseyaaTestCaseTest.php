<?php

declare(strict_types=1);

namespace Waaseyaa\Testing\Tests\Unit;

use Waaseyaa\Testing\WaaseyaaTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests the WaaseyaaTestCase base class via a concrete subclass.
 */
#[CoversClass(WaaseyaaTestCase::class)]
final class WaaseyaaTestCaseTest extends TestCase
{
    #[Test]
    public function assertArrayHasKeysPassesWhenAllKeysPresent(): void
    {
        $testCase = new ConcreteTestCase('test');
        $testCase->runAssertArrayHasKeys(
            ['foo', 'bar'],
            ['foo' => 1, 'bar' => 2, 'baz' => 3],
        );

        // If we get here without exception, the assertion passed.
        $this->assertTrue(true);
    }

    #[Test]
    public function assertNonEmptyStringPassesForNonEmptyString(): void
    {
        $testCase = new ConcreteTestCase('test');
        $testCase->runAssertNonEmptyString('hello');

        $this->assertTrue(true);
    }

    #[Test]
    public function assertSameValuesPassesRegardlessOfOrder(): void
    {
        $testCase = new ConcreteTestCase('test');
        $testCase->runAssertSameValues([3, 1, 2], [1, 2, 3]);

        $this->assertTrue(true);
    }

    #[Test]
    public function serviceRegistrationAndRetrieval(): void
    {
        $testCase = new ConcreteTestCase('test');
        $testCase->callSetUp();

        $service = new \stdClass();
        $testCase->callRegisterService('my_service', $service);

        $this->assertTrue($testCase->callHasService('my_service'));
        $this->assertSame($service, $testCase->callGetService('my_service'));

        $testCase->callTearDown();
    }

    #[Test]
    public function getServiceThrowsForUnregistered(): void
    {
        $testCase = new ConcreteTestCase('test');
        $testCase->callSetUp();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Service "nonexistent" is not registered');

        $testCase->callGetService('nonexistent');
    }

    #[Test]
    public function authTraitActingAs(): void
    {
        $testCase = new ConcreteTestCase('test');
        $testCase->callSetUp();

        $testCase->callAssertGuest();

        $user = ['uid' => 1, 'name' => 'admin'];
        $testCase->callActingAs($user);
        $testCase->callAssertAuthenticated();

        $this->assertSame($user, $testCase->callGetCurrentUser());

        $testCase->callActingAsGuest();
        $testCase->callAssertGuest();

        $testCase->callTearDown();
    }

    #[Test]
    public function eventTraitRecordAndAssert(): void
    {
        $testCase = new ConcreteTestCase('test');
        $testCase->callSetUp();

        $testCase->callRecordEvent('user.login', ['uid' => 1]);
        $testCase->callRecordEvent('user.login', ['uid' => 2]);

        $testCase->callAssertEventDispatched('user.login');
        $testCase->callAssertEventDispatched('user.login', 2);
        $testCase->callAssertEventNotDispatched('user.logout');

        $testCase->callTearDown();
    }

    #[Test]
    public function eventTraitFakeEvents(): void
    {
        $testCase = new ConcreteTestCase('test');
        $testCase->callSetUp();

        $testCase->callFakeEvents(['user.login']);

        $this->assertTrue($testCase->callIsEventsFaked());

        $testCase->callTearDown();

        // After tearDown, events should be reset.
        $this->assertFalse($testCase->callIsEventsFaked());
    }

    #[Test]
    public function tearDownResetsAuthAndEvents(): void
    {
        $testCase = new ConcreteTestCase('test');
        $testCase->callSetUp();

        $testCase->callActingAs(['uid' => 1, 'name' => 'test']);
        $testCase->callRecordEvent('some.event');
        $testCase->callFakeEvents();

        $testCase->callTearDown();

        // After tearDown, auth and events are reset.
        $this->assertNull($testCase->callGetCurrentUser());
        $this->assertFalse($testCase->callIsEventsFaked());
        $this->assertSame([], $testCase->callGetDispatchedEvents());
    }
}

/**
 * Concrete subclass of WaaseyaaTestCase for testing purposes.
 *
 * Exposes protected methods as public so we can call them from the test.
 */
final class ConcreteTestCase extends WaaseyaaTestCase
{
    public function callSetUp(): void
    {
        $this->setUp();
    }

    public function callTearDown(): void
    {
        $this->tearDown();
    }

    /** @param string[] $keys */
    public function runAssertArrayHasKeys(array $keys, array $array): void
    {
        $this->assertArrayHasKeys($keys, $array);
    }

    public function runAssertNonEmptyString(mixed $value): void
    {
        $this->assertNonEmptyString($value);
    }

    public function runAssertSameValues(array $expected, array $actual): void
    {
        $this->assertSameValues($expected, $actual);
    }

    public function callRegisterService(string $name, object $service): void
    {
        $this->registerService($name, $service);
    }

    public function callGetService(string $name): object
    {
        return $this->getService($name);
    }

    public function callHasService(string $name): bool
    {
        return $this->hasService($name);
    }

    /** @param array<string, mixed> $user */
    public function callActingAs(array $user): void
    {
        $this->actingAs($user);
    }

    public function callActingAsGuest(): void
    {
        $this->actingAsGuest();
    }

    public function callAssertAuthenticated(): void
    {
        $this->assertAuthenticated();
    }

    public function callAssertGuest(): void
    {
        $this->assertGuest();
    }

    /** @return array<string, mixed>|null */
    public function callGetCurrentUser(): ?array
    {
        return $this->getCurrentUser();
    }

    public function callRecordEvent(string $eventName, mixed $payload = null): void
    {
        $this->recordEvent($eventName, $payload);
    }

    public function callAssertEventDispatched(string $eventName, ?int $times = null): void
    {
        $this->assertEventDispatched($eventName, $times);
    }

    public function callAssertEventNotDispatched(string $eventName): void
    {
        $this->assertEventNotDispatched($eventName);
    }

    /** @param string[] $events */
    public function callFakeEvents(array $events = []): void
    {
        $this->fakeEvents($events);
    }

    public function callIsEventsFaked(): bool
    {
        return $this->isEventsFaked();
    }

    /** @return array<string, array<int, mixed>> */
    public function callGetDispatchedEvents(): array
    {
        return $this->getDispatchedEvents();
    }
}
