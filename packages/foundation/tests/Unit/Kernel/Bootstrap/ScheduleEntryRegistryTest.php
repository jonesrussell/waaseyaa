<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Kernel\Bootstrap;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Discovery\PackageManifest;
use Waaseyaa\Foundation\Kernel\Bootstrap\Exception\PolicyInstantiationException;
use Waaseyaa\Foundation\Kernel\Bootstrap\Exception\ScheduleEntryInstantiationException;
use Waaseyaa\Foundation\Kernel\Bootstrap\PolicyDependencyResolverInterface;
use Waaseyaa\Foundation\Kernel\Bootstrap\ScheduleEntryRegistry;
use Waaseyaa\Foundation\Log\NullLogger;
use Waaseyaa\Scheduler\Schedule;
use Waaseyaa\Scheduler\ScheduleEntriesInterface;
use Waaseyaa\Scheduler\ScheduleInterface;
use Waaseyaa\Scheduler\ScheduledTask;

#[CoversClass(ScheduleEntryRegistry::class)]
final class ScheduleEntryRegistryTest extends TestCase
{
    // T011 — FR-010: registersScheduleEntriesAtBoot
    #[Test]
    public function registers_schedule_entries_at_boot(): void
    {
        $registerCallCount = 0;

        $entryClass = self::createSpyScheduleEntries(static function () use (&$registerCallCount): void {
            $registerCallCount++;
        });

        $manifest = new PackageManifest(
            scheduleEntries: [$entryClass],
        );

        $resolver = self::noOpResolver();
        $schedule = new Schedule();

        (new ScheduleEntryRegistry(new NullLogger(), $resolver))
            ->boot($manifest, $schedule, []);

        self::assertSame(1, $registerCallCount, 'register() must be called exactly once per manifest entry');
    }

    // T012 — FR-011: failsBootOnUnresolvableScheduleEntry
    #[Test]
    public function fails_boot_on_unresolvable_schedule_entry(): void
    {
        $entryClass = self::createEntryWithServiceDep();

        $resolver = new class implements PolicyDependencyResolverInterface {
            public function resolveParameter(string $policyClass, \ReflectionParameter $param, array $entityTypes): mixed
            {
                throw new PolicyInstantiationException(
                    sprintf('Cannot resolve %s for %s', $param->getName(), $policyClass),
                );
            }
        };

        $manifest = new PackageManifest(
            scheduleEntries: [$entryClass],
        );

        $schedule = new Schedule();

        $this->expectException(ScheduleEntryInstantiationException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote($entryClass, '/') . '/');

        (new ScheduleEntryRegistry(new NullLogger(), $resolver))
            ->boot($manifest, $schedule, []);
    }

    // T013 — SC-004: skipsDisabledScheduleEntries
    #[Test]
    public function skips_disabled_schedule_entries(): void
    {
        $enabledCallCount  = 0;
        $disabledCallCount = 0;

        $enabledClass  = self::createSpyScheduleEntries(static function () use (&$enabledCallCount): void {
            $enabledCallCount++;
        });
        $disabledClass = self::createSpyScheduleEntries(static function () use (&$disabledCallCount): void {
            $disabledCallCount++;
        });

        $manifest = new PackageManifest(
            scheduleEntries: [$enabledClass, $disabledClass],
        );

        $config = [
            'schedule' => [
                'disabled_entries' => [$disabledClass],
            ],
        ];

        $resolver = self::noOpResolver();
        $schedule = new Schedule();

        (new ScheduleEntryRegistry(new NullLogger(), $resolver))
            ->boot($manifest, $schedule, $config);

        self::assertSame(1, $enabledCallCount, 'Enabled entry register() must be called');
        self::assertSame(0, $disabledCallCount, 'Disabled entry register() must NOT be called');
    }

    /**
     * Creates a ScheduleEntriesInterface class whose register() invokes the spy callback.
     *
     * @return class-string
     */
    private static function createSpyScheduleEntries(\Closure $spy): string
    {
        $className = 'SpyScheduleEntries_' . uniqid();
        // Capture spy in static property via eval to avoid closure-in-eval issues.
        $spyKey = 'spy_' . $className;
        $GLOBALS[$spyKey] = $spy;

        eval(sprintf(
            'final class %s implements %s {
                public function register(%s $schedule): array {
                    ($GLOBALS["%s"])();
                    return [];
                }
            }',
            $className,
            ScheduleEntriesInterface::class,
            ScheduleInterface::class,
            $spyKey,
        ));

        /** @var class-string */
        return $className;
    }

    /**
     * Creates a ScheduleEntriesInterface class that requires a non-existent service in its constructor.
     *
     * @return class-string
     */
    private static function createEntryWithServiceDep(): string
    {
        $className = 'UnresolvableScheduleEntries_' . uniqid();
        eval(sprintf(
            'final class %s implements %s {
                public function __construct(private readonly %s $dep) {}
                public function register(%s $schedule): array { return []; }
            }',
            $className,
            ScheduleEntriesInterface::class,
            Schedule::class,
            ScheduleInterface::class,
        ));

        /** @var class-string */
        return $className;
    }

    /**
     * A resolver that should never be called (for zero-dep entries).
     */
    private static function noOpResolver(): PolicyDependencyResolverInterface
    {
        return new class implements PolicyDependencyResolverInterface {
            public function resolveParameter(string $policyClass, \ReflectionParameter $param, array $entityTypes): mixed
            {
                throw new PolicyInstantiationException('Unexpected: resolver called for zero-dep entry');
            }
        };
    }
}
