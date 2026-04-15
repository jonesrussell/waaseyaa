<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Tests\Unit\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Observability\Value\DecisionTrace;

#[CoversClass(DecisionTrace::class)]
final class DecisionTraceTest extends TestCase
{
    #[Test]
    public function it_holds_decision_fields(): void
    {
        $d = new DecisionTrace(
            question: 'which model?',
            chosen: 'claude-opus-4-6',
            alternatives: ['gpt-4o', 'claude-sonnet-4-6'],
            reasoning: 'needs deep reasoning',
            confidence: 0.9,
        );

        self::assertSame('which model?', $d->question);
        self::assertSame('claude-opus-4-6', $d->chosen);
        self::assertSame(['gpt-4o', 'claude-sonnet-4-6'], $d->alternatives);
        self::assertSame(0.9, $d->confidence);
    }

    #[Test]
    public function to_attributes_returns_full_payload(): void
    {
        $d = new DecisionTrace('q', 'a', ['b'], 'r', 0.5);
        self::assertSame([
            'question' => 'q',
            'chosen' => 'a',
            'alternatives' => ['b'],
            'reasoning' => 'r',
            'confidence' => 0.5,
        ], $d->toAttributes());
    }

    #[Test]
    public function confidence_must_be_between_zero_and_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DecisionTrace('q', 'a', [], 'r', 1.5);
    }
}
