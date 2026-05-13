<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Runner;

/**
 * One per-record failure captured inside a {@see RunReport}.
 *
 * The runner caps the per-record error list at {@see RunReport::ERROR_CAP}
 * to keep memory bounded on million-error runs. WP07's `migration_run_state`
 * row carries the full audit trail; the in-memory report carries the first N.
 *
 * Stage indicates which pipeline phase raised the error:
 *  - `'source'`      → {@see \Waaseyaa\Migration\Exception\SourceReadException}
 *  - `'process'`     → {@see \Waaseyaa\Migration\Exception\ProcessException}
 *  - `'destination'` → {@see \Waaseyaa\Migration\Exception\DestinationWriteException}
 *
 * @api
 *
 * @spec FR-046 — per-record error capture surface
 */
final readonly class RecordError
{
    public const string STAGE_SOURCE = 'source';
    public const string STAGE_PROCESS = 'process';
    public const string STAGE_DESTINATION = 'destination';

    /**
     * @param string $sourceIdHash Canonical SHA-256 hash of the offending source identity. `'unknown'` when the failure happened before a `SourceId` could be derived.
     * @param string $code Stable string code from the underlying typed exception. Non-empty.
     * @param string $message Operator-friendly message. Non-empty.
     * @param string $stage One of {@see self::STAGE_SOURCE}, {@see self::STAGE_PROCESS}, {@see self::STAGE_DESTINATION}.
     * @param string|null $sourceField Source field name when known (process stage); `null` otherwise.
     *
     * @throws \InvalidArgumentException When any required string is empty or `$stage` is unrecognised.
     */
    public function __construct(
        public string $sourceIdHash,
        public string $code,
        public string $message,
        public string $stage,
        public ?string $sourceField = null,
    ) {
        if ($sourceIdHash === '') {
            throw new \InvalidArgumentException('RecordError::$sourceIdHash must be a non-empty string.');
        }
        if ($code === '') {
            throw new \InvalidArgumentException('RecordError::$code must be a non-empty string.');
        }
        if ($message === '') {
            throw new \InvalidArgumentException('RecordError::$message must be a non-empty string.');
        }
        if (
            $stage !== self::STAGE_SOURCE
            && $stage !== self::STAGE_PROCESS
            && $stage !== self::STAGE_DESTINATION
        ) {
            throw new \InvalidArgumentException(\sprintf(
                'RecordError::$stage must be one of %s, %s, %s; got %s.',
                self::STAGE_SOURCE,
                self::STAGE_PROCESS,
                self::STAGE_DESTINATION,
                \var_export($stage, true),
            ));
        }
        if ($sourceField !== null && $sourceField === '') {
            throw new \InvalidArgumentException('RecordError::$sourceField must be null or non-empty.');
        }
    }
}
