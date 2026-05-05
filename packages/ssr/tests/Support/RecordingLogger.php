<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Tests\Support;

use Waaseyaa\Foundation\Log\LogLevel;
use Waaseyaa\Foundation\Log\LoggerInterface;

/**
 * Test double that captures every log emission as `[level, message, context]`
 * triples. Implements the project's logger contract directly — no external
 * dependencies. Used by the dispatcher-array-param-compat-shim mission tests.
 */
final class RecordingLogger implements LoggerInterface
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $entries = [];

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->record('emergency', $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->record('alert', $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->record('critical', $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->record('error', $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->record('warning', $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->record('notice', $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->record('info', $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->record('debug', $message, $context);
    }

    public function log(LogLevel $level, string|\Stringable $message, array $context = []): void
    {
        $this->record($level->value, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function record(string $level, string|\Stringable $message, array $context): void
    {
        $this->entries[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
