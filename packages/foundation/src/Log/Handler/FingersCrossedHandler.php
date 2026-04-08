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
    private const int DEFAULT_BUFFER_LIMIT = 10000;

    /** @var list<LogRecord> */
    private array $buffer = [];

    /**
     * @param int $bufferLimit Max records to retain below the action level; oldest dropped when full.
     *                        Use 0 for no limit (not recommended in production).
     */
    public function __construct(
        private readonly HandlerInterface $handler,
        private readonly LogLevel $actionLevel = LogLevel::ERROR,
        private readonly int $bufferLimit = self::DEFAULT_BUFFER_LIMIT,
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

        if ($this->bufferLimit > 0 && count($this->buffer) >= $this->bufferLimit) {
            array_shift($this->buffer);
        }
        $this->buffer[] = $record;
    }
}
