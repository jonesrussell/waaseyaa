<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Ingestion;

/**
 * Appends and reads ingestion log entries from a JSONL file.
 *
 * Entries are stored at storage/framework/ingestion.jsonl.
 * Atomic append via FILE_APPEND | LOCK_EX prevents interleaving.
 * Default retention is 90 days — call prune() periodically to enforce it.
 */
final class IngestionLogger
{
    public const int DEFAULT_RETENTION_DAYS = 90;

    private const LOG_FILE = '/storage/framework/ingestion.jsonl';

    public function __construct(private readonly string $projectRoot) {}

    public function log(IngestionLogEntry $entry): void
    {
        $file = $this->logFile();
        $this->ensureDirectory(dirname($file));

        file_put_contents(
            $file,
            json_encode($entry->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX,
        );
    }

    /**
     * Read log entries, optionally filtered by status.
     *
     * @return list<array<string, mixed>>
     */
    public function read(string $statusFilter = ''): array
    {
        $file = $this->logFile();

        if (!file_exists($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        $entries = [];

        foreach ($lines as $line) {
            try {
                /** @var array<string, mixed> $entry */
                $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

                if ($statusFilter === '' || ($entry['status'] ?? '') === $statusFilter) {
                    $entries[] = $entry;
                }
            } catch (\JsonException) {
                // Skip malformed lines without crashing.
            }
        }

        return $entries;
    }

    /**
     * Remove entries older than $retentionDays. Rewrites the log atomically.
     */
    public function prune(int $retentionDays = self::DEFAULT_RETENTION_DAYS): void
    {
        $file = $this->logFile();

        if (!file_exists($file)) {
            return;
        }

        $cutoff  = new \DateTimeImmutable("-{$retentionDays} days");
        $entries = $this->read();
        $kept    = [];

        foreach ($entries as $entry) {
            try {
                $ts = new \DateTimeImmutable((string) ($entry['logged_at'] ?? ''));

                if ($ts >= $cutoff) {
                    $kept[] = $entry;
                }
            } catch (\Throwable) {
                $kept[] = $entry; // Keep unparseable entries rather than silently drop.
            }
        }

        $tmp = $file . '.tmp.' . getmypid();
        file_put_contents(
            $tmp,
            implode("\n", array_map(
                static fn(array $e): string => json_encode($e, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                $kept,
            )) . ($kept !== [] ? "\n" : ''),
        );
        rename($tmp, $file);
    }

    private function logFile(): string
    {
        return $this->projectRoot . self::LOG_FILE;
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }
    }
}
