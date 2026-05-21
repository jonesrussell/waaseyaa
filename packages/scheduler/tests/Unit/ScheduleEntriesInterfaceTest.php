<?php

declare(strict_types=1);

namespace Waaseyaa\Scheduler\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Waaseyaa\Scheduler\ScheduleEntriesInterface;
use Waaseyaa\Scheduler\ScheduleInterface;

#[CoversNothing]
final class ScheduleEntriesInterfaceTest extends TestCase
{
    #[Test]
    public function interfaceDeclaresSingleMethod(): void
    {
        $methods = (new \ReflectionClass(ScheduleEntriesInterface::class))->getMethods();
        self::assertCount(1, $methods);
        self::assertSame('register', $methods[0]->getName());
    }

    #[Test]
    public function registerMethodAcceptsScheduleInterface(): void
    {
        $method = new ReflectionMethod(ScheduleEntriesInterface::class, 'register');
        $params = $method->getParameters();
        self::assertCount(1, $params);
        self::assertSame('schedule', $params[0]->getName());
        self::assertSame(ScheduleInterface::class, $params[0]->getType()->getName());
    }

    #[Test]
    public function registerMethodReturnTypeIsArray(): void
    {
        $method = new ReflectionMethod(ScheduleEntriesInterface::class, 'register');
        self::assertSame('array', $method->getReturnType()->getName());
    }
}
