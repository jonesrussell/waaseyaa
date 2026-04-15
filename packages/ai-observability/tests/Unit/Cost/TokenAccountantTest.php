<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Tests\Unit\Cost;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Observability\Cost\ModelPricing;
use Waaseyaa\AI\Observability\Cost\TokenAccountant;
use Waaseyaa\AI\Observability\Handle\TraceHandle;
use Waaseyaa\AI\Observability\Recorder\NullTraceRecorder;

#[CoversClass(TokenAccountant::class)]
final class TokenAccountantTest extends TestCase
{
    #[Test]
    public function computesCostFromPricing(): void
    {
        $pricing = new ModelPricing(['m' => ['input' => 3.0, 'output' => 15.0, 'cached' => 0.3]]);
        $accountant = new TokenAccountant(new NullTraceRecorder(), $pricing);
        $handle = new TraceHandle('t', new \DateTimeImmutable());

        $record = $accountant->record($handle, 'm', inputTokens: 1_000_000, outputTokens: 1_000_000, cachedTokens: 1_000_000);

        self::assertSame('m', $record->model);
        self::assertSame(18.3, $record->costUsd);
    }

    #[Test]
    public function unknownModelYieldsZeroCost(): void
    {
        $accountant = new TokenAccountant(new NullTraceRecorder(), new ModelPricing());
        $handle = new TraceHandle('t', new \DateTimeImmutable());

        $record = $accountant->record($handle, 'bogus', 100, 100);

        self::assertSame(0.0, $record->costUsd);
    }
}
