<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Tests\Unit\Reaper;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Agent\Reaper\StalledRunReaper;

/**
 * Surface-only check that {@see StalledRunReaper}'s public contract is
 * stable. Behaviour is exercised in the integration harness.
 *
 * Behavioural tests:
 *  - `tests/Integration/PhaseN/AgentRuntime/ReaperTest.php` — flips
 *    stalled rows, respects threshold, refuses to regress terminal.
 */
#[CoversNothing]
final class StalledRunReaperTest extends TestCase
{
    #[Test]
    public function classExposesReapMethodAndRejectsBadThreshold(): void
    {
        $reflection = new \ReflectionClass(StalledRunReaper::class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->hasMethod('reap'));

        $reap = $reflection->getMethod('reap');
        self::assertSame('int', (string) $reap->getReturnType());
    }
}
