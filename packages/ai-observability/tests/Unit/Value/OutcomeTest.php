<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Tests\Unit\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Observability\Value\Outcome;

#[CoversClass(Outcome::class)]
final class OutcomeTest extends TestCase
{
    #[Test]
    public function it_holds_status_and_feedback(): void
    {
        $o = new Outcome('accepted', 'LGTM', ['reviewer' => 'russ']);
        self::assertSame('accepted', $o->status);
        self::assertSame('LGTM', $o->feedback);
        self::assertSame(['reviewer' => 'russ'], $o->metadata);
    }

    #[Test]
    public function status_must_be_known_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Outcome('bogus');
    }
}
