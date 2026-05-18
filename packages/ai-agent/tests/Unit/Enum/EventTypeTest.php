<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Agent\Enum\EventType;

#[CoversClass(EventType::class)]
final class EventTypeTest extends TestCase
{
    #[Test]
    public function caseValuesMatchDataModel(): void
    {
        self::assertSame('iteration_start', EventType::IterationStart->value);
        self::assertSame('tool_call', EventType::ToolCall->value);
        self::assertSame('tool_result', EventType::ToolResult->value);
        self::assertSame('provider_call', EventType::ProviderCall->value);
        self::assertSame('approval_required', EventType::ApprovalRequired->value);
        self::assertSame('approval_granted', EventType::ApprovalGranted->value);
        self::assertSame('approval_denied', EventType::ApprovalDenied->value);
        self::assertSame('error', EventType::Error->value);
    }
}
