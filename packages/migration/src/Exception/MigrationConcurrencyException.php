<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Exception;

/**
 * Thrown when an `import:*` command tries to acquire a per-migration
 * filesystem lock that is already held by another process.
 *
 * Carries the lock-file path and (when readable) the PID written by the
 * holder — both are operator-actionable for the manual stale-lock recovery
 * path documented on the message body.
 *
 * Per spec §9.3 (decision D11) the framework deliberately does NOT
 * auto-remove stale locks: PID reuse on long-running hosts would otherwise
 * make accidental concurrent runs silent. When the holding PID is dead the
 * operator must remove the lock file by hand.
 *
 * @api
 *
 * @spec FR-061 — per-migration concurrency lock
 * @spec FR-062 — graceful release on SIGTERM/SIGINT (recovery path)
 */
final class MigrationConcurrencyException extends \RuntimeException
{
    /**
     * Stable string code shared across SAPIs. Operators distinguish this
     * exception from `MigrationAbortedException` via the class FQCN, but
     * the `CODE` constant is the on-the-wire identifier when this surface
     * grows JSON output.
     */
    public const string CODE = 'MIGRATION_CONCURRENT_RUN';

    /**
     * @param string $migrationId Migration id whose lock could not be acquired. Non-empty.
     * @param string $lockPath Absolute path to the lock file currently held by another process. Non-empty.
     * @param int|null $holdingPid PID parsed from the existing lock file, or null when the file is empty / unreadable.
     *
     * @throws \InvalidArgumentException When `$migrationId` or `$lockPath` is empty.
     */
    public function __construct(
        public readonly string $migrationId,
        public readonly string $lockPath,
        public readonly ?int $holdingPid,
    ) {
        if ($migrationId === '') {
            throw new \InvalidArgumentException(
                'MigrationConcurrencyException::$migrationId must be a non-empty string.',
            );
        }
        if ($lockPath === '') {
            throw new \InvalidArgumentException(
                'MigrationConcurrencyException::$lockPath must be a non-empty string.',
            );
        }

        $pidLabel = $holdingPid !== null ? (string) $holdingPid : 'unknown';

        parent::__construct(\sprintf(
            "Migration '%s' is already running (lock: %s, pid: %s).\n"
            . "If the holding process is no longer alive, manually remove the lock file:\n"
            . '    rm %s',
            $migrationId,
            $lockPath,
            $pidLabel,
            $lockPath,
        ));
    }
}
