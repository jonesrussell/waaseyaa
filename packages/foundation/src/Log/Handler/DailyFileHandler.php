<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Log\Handler;

use Waaseyaa\Foundation\Log\Formatter\FormatterInterface;
use Waaseyaa\Foundation\Log\Formatter\TextFormatter;
use Waaseyaa\Foundation\Log\LogLevel;
use Waaseyaa\Foundation\Log\LogRecord;

/**
 * Appends log lines to a file whose name includes the current UTC date.
 *
 * Given path "storage/logs/waaseyaa.log", writes to "storage/logs/waaseyaa-2026-04-08.log".
 */
final class DailyFileHandler implements HandlerInterface
{
    private const string DATE_FORMAT = 'Y-m-d';

    private readonly FormatterInterface $formatter;

    private string $lastDate = '';

    public function __construct(
        private readonly string $filePathPattern,
        ?FormatterInterface $formatter = null,
        private readonly LogLevel $minimumLevel = LogLevel::DEBUG,
    ) {
        $this->formatter = $formatter ?? new TextFormatter();
    }

    public function handle(LogRecord $record): void
    {
        if ($record->level->severity() < $this->minimumLevel->severity()) {
            return;
        }

        $date = $record->timestamp->format(self::DATE_FORMAT);
        if ($date !== $this->lastDate) {
            $this->lastDate = $date;
        }

        $path = $this->resolvePath($date);
        $directory = \dirname($path);
        if ($directory !== '' && $directory !== '.' && !is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }

        $line = $this->formatter->format($record) . "\n";
        file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param non-empty-string $date
     */
    private function resolvePath(string $date): string
    {
        if (str_contains($this->filePathPattern, '{date}')) {
            return str_replace('{date}', $date, $this->filePathPattern);
        }

        if (preg_match('/^(.*?)(\.[^.]+)$/', $this->filePathPattern, $matches) === 1) {
            return $matches[1] . '-' . $date . $matches[2];
        }

        return $this->filePathPattern . '-' . $date;
    }
}
