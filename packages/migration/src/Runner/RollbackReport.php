<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Runner;

/**
 * Output value object summarising one {@see RollbackWalker::rollback()} call.
 *
 * The walker is best-effort: per-record failures are captured here but do
 * NOT halt the walk (FR-044). The CLI command renders this report on
 * stdout and uses {@see $failed} to pick its exit code (0 vs 1).
 *
 * @api
 *
 * @spec FR-044 — best-effort + per-record reporting
 */
final readonly class RollbackReport
{
    /**
     * Maximum number of {@see RollbackError} entries retained in memory.
     * Operators expecting a fuller audit trail should consult the
     * structured log records emitted on `entity.lifecycle`.
     */
    public const int ERROR_CAP = 100;

    /**
     * @param string                $migrationId   Logical id of the migration that was rolled back. Non-empty.
     * @param int                   $visited       Count of id-map rows the walker iterated.
     * @param int                   $rolledBack    Count of rows successfully rolled back (destination entity deleted + id-map row removed).
     * @param int                   $failed        Count of rows whose rollback raised an exception. Equal to `count($errors)` when failed <= ERROR_CAP; otherwise larger (the tail is dropped from the in-memory list but counted).
     * @param list<RollbackError>   $errors        Captured per-record errors. Capped at {@see ERROR_CAP}.
     * @param \DateTimeImmutable    $startedAt     Wall-clock start of the walk.
     * @param \DateTimeImmutable    $finishedAt   Wall-clock end of the walk.
     */
    public function __construct(
        public string $migrationId,
        public int $visited,
        public int $rolledBack,
        public int $failed,
        public array $errors,
        public \DateTimeImmutable $startedAt,
        public \DateTimeImmutable $finishedAt,
    ) {
        if ($migrationId === '') {
            throw new \InvalidArgumentException(
                'RollbackReport::$migrationId must be a non-empty string.',
            );
        }
        if ($visited < 0) {
            throw new \InvalidArgumentException(
                'RollbackReport::$visited must be >= 0.',
            );
        }
        if ($rolledBack < 0) {
            throw new \InvalidArgumentException(
                'RollbackReport::$rolledBack must be >= 0.',
            );
        }
        if ($failed < 0) {
            throw new \InvalidArgumentException(
                'RollbackReport::$failed must be >= 0.',
            );
        }
        if ($rolledBack + $failed > $visited) {
            throw new \InvalidArgumentException(\sprintf(
                'RollbackReport: rolledBack (%d) + failed (%d) exceeds visited (%d).',
                $rolledBack,
                $failed,
                $visited,
            ));
        }
        if (\count($errors) > self::ERROR_CAP) {
            throw new \InvalidArgumentException(\sprintf(
                'RollbackReport::$errors must contain at most %d entries (got %d).',
                self::ERROR_CAP,
                \count($errors),
            ));
        }
        if ($finishedAt < $startedAt) {
            throw new \InvalidArgumentException(
                'RollbackReport::$finishedAt must be >= $startedAt.',
            );
        }
    }

    /**
     * One-line CLI-friendly summary suitable for stdout.
     *
     * Format: `"<migrationId>: rollback complete (<rolledBack>/<visited>, <failed> failed)"`.
     */
    public function summaryLine(): string
    {
        return \sprintf(
            '%s: rollback complete (%d/%d, %d failed)',
            $this->migrationId,
            $this->rolledBack,
            $this->visited,
            $this->failed,
        );
    }
}
