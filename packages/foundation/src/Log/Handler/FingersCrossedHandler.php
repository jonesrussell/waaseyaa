<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Log\Handler;

use Waaseyaa\Foundation\Log\LogLevel;
use Waaseyaa\Foundation\Log\LogRecord;

/**
 * Buffers log records in memory and forwards them only after a record at or
 * above {@see $actionLevel} is received; otherwise the buffer is discarded.
 */
final class FingersCrossedHandler implements HandlerInterface
{
    /** @var list<LogRecord> */
    private array $buffer = [];

    public function __construct(
        private readonly HandlerInterface $handler,
        private readonly LogLevel $actionLevel = LogLevel::ERROR,
    ) {}

    public function handle(LogRecord $record): void
    {
        if ($record->level->severity() >= $this->actionLevel->severity()) {
            foreach ($this->buffer as $buffered) {
                $this->handler->handle($buffered);
            }
            $this->buffer = [];
            $this->handler->handle($record);

            return;
        }

        $this->buffer[] = $record;
    }
}
