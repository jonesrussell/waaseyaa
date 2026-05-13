<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Exception;

/**
 * Thrown when a source plugin fails to read its upstream system.
 *
 * Bridges plugin-level errors (HTTP failure, malformed feed, IO error, etc.)
 * to the migration runner so per-record error reporting (FR-046) and
 * halt-on-error semantics (FR-047) can act on a typed signal rather than a
 * generic `\Throwable`.
 *
 * Carries the offending source plugin id and the migration id so log
 * readers can correlate the failure with the running migration without
 * parsing the message string.
 *
 * @api
 *
 * @spec FR-045 — stable exception types with `code` field
 */
final class SourceReadException extends \RuntimeException
{
    /**
     * Stable string code (FR-045). Tools and log analyzers index on this,
     * not on the FQCN.
     */
    public const string CODE = 'SOURCE_IO_ERROR';

    /**
     * @param string $sourceId Source-plugin id that failed (e.g. `wordpress_post`). Non-empty.
     * @param string $migrationId Id of the running migration whose source read failed. Non-empty.
     * @param string $reason Operator-friendly explanation of the failure (the upstream message or a wrapping summary). Non-empty.
     * @param \Throwable|null $previous Optional wrapped exception from the underlying IO/parse layer.
     *
     * @throws \InvalidArgumentException When any required string is empty.
     */
    public function __construct(
        public readonly string $sourceId,
        public readonly string $migrationId,
        string $reason,
        ?\Throwable $previous = null,
    ) {
        if ($sourceId === '') {
            throw new \InvalidArgumentException('SourceReadException::$sourceId must be a non-empty string.');
        }
        if ($migrationId === '') {
            throw new \InvalidArgumentException('SourceReadException::$migrationId must be a non-empty string.');
        }
        if ($reason === '') {
            throw new \InvalidArgumentException('SourceReadException::$reason must be a non-empty string.');
        }

        parent::__construct(\sprintf(
            "Source plugin '%s' failed for migration '%s': %s",
            $sourceId,
            $migrationId,
            $reason,
        ), code: 0, previous: $previous);
    }
}
