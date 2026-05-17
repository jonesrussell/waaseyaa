<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Unit\Audit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Config\Audit\ConfigAuditChannel;

#[CoversClass(ConfigAuditChannel::class)]
final class ConfigAuditChannelTest extends TestCase
{
    #[Test]
    public function channel_constant_matches_charter_amendment(): void
    {
        // Stable surface (charter §4.4 amendment + FR-053): the literal
        // channel name 'config.audit' is the operator-facing contract.
        self::assertSame('config.audit', ConfigAuditChannel::CHANNEL);
    }

    #[Test]
    public function class_is_not_instantiable(): void
    {
        $reflection = new \ReflectionClass(ConfigAuditChannel::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate(), 'ConfigAuditChannel must not be instantiable.');
    }
}
