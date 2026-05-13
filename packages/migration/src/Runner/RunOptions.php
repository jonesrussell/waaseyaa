<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Runner;

/**
 * Input value object capturing per-run flags for {@see MigrationRunner::run()}.
 *
 * Carved out of the CLI surface so the runner core has no `CliIO` coupling —
 * the three `import:*` commands assemble a `RunOptions` from their parsed
 * flags and hand it to the runner; tests instantiate it directly.
 *
 * Fields map to FRs:
 *  - `$dryRun`       → FR-039 (process steps run; destination `write()` is
 *    skipped; the record counts as "skipped" in the report).
 *  - `$haltOnError`  → FR-047 (per-record error halts the run after the
 *    failing record is captured).
 *  - `$limit`        → FR-040 (process only the first N source records).
 *  - `$runId`        → operator override for the generated run id; primarily
 *    a CI / regression-test seam.
 *
 * @api
 *
 * @spec FR-039 — dry-run flag
 * @spec FR-040 — limit option
 * @spec FR-047 — halt-on-error flag
 */
final readonly class RunOptions
{
    /**
     * UUIDv7 surface check — rfc4122 with version-7 nibble.
     *
     * The literal is intentionally permissive (any rfc4122 v7-shaped value);
     * full byte-level validation lives in `symfony/uid`'s parser. Callers
     * that need the canonical surface should generate via `UuidV7::generate()`.
     */
    private const string UUIDV7_PATTERN
        = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /**
     * @param bool $dryRun Execute source + process steps but skip destination writes (FR-039). Each source record counts as "skipped" in the {@see RunReport}.
     * @param bool $haltOnError Halt on the first per-record error after capturing it in the report (FR-047). Default `false` continues past per-record errors.
     * @param int|null $limit Process only the first N source records (FR-040). `null` (default) means unlimited. Must be `> 0` when supplied.
     * @param string|null $runId Override the generated UUIDv7 run id. Defaults to `null`; the runner mints one. When supplied must match a UUIDv7 surface shape.
     *
     * @throws \InvalidArgumentException When `$limit` is not positive or `$runId` is set but malformed.
     */
    public function __construct(
        public bool $dryRun = false,
        public bool $haltOnError = false,
        public ?int $limit = null,
        public ?string $runId = null,
    ) {
        if ($limit !== null && $limit <= 0) {
            throw new \InvalidArgumentException(\sprintf(
                'RunOptions::$limit must be a positive integer when supplied, got %s.',
                \var_export($limit, true),
            ));
        }
        if ($runId !== null) {
            if ($runId === '') {
                throw new \InvalidArgumentException(
                    'RunOptions::$runId must be a non-empty UUIDv7 string when supplied.',
                );
            }
            if (\preg_match(self::UUIDV7_PATTERN, $runId) !== 1) {
                throw new \InvalidArgumentException(\sprintf(
                    'RunOptions::$runId must match the UUIDv7 surface shape, got %s.',
                    \var_export($runId, true),
                ));
            }
        }
    }
}
