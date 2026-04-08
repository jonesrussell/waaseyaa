<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Log;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Log\Handler\FingersCrossedHandler;
use Waaseyaa\Foundation\Log\Handler\HandlerInterface;
use Waaseyaa\Foundation\Log\LogLevel;
use Waaseyaa\Foundation\Log\LogRecord;

#[CoversClass(FingersCrossedHandler::class)]
final class FingersCrossedHandlerTest extends TestCase
{
    #[Test]
    public function drops_oldest_buffered_records_when_limit_reached(): void
    {
        $written = [];
        $inner = new class ($written) implements HandlerInterface {
            public function __construct(private array &$written) {}

            public function handle(LogRecord $record): void
            {
                $this->written[] = $record->message;
            }
        };

        $handler = new FingersCrossedHandler($inner, LogLevel::ERROR, bufferLimit: 2);
        $handler->handle(self::record(LogLevel::INFO, 'a'));
        $handler->handle(self::record(LogLevel::INFO, 'b'));
        $handler->handle(self::record(LogLevel::INFO, 'c'));
        $handler->handle(self::record(LogLevel::ERROR, 'err'));

        self::assertSame(['b', 'c', 'err'], $written);
    }

    private static function record(LogLevel $level, string $message): LogRecord
    {
        return new LogRecord(
            level: $level,
            message: $message,
            context: [],
            channel: 'test',
        );
    }
}
