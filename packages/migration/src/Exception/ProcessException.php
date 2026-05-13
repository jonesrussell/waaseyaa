<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Exception;

/**
 * Thrown by a {@see \Waaseyaa\Migration\Plugin\ProcessPluginInterface}
 * implementation when transforming a single value fails in a way the runner
 * can attribute to one source field of one record.
 *
 * Per-record process failures are recoverable at the run level: the runner
 * decides — based on `--halt-on-error` and the configured error-rate budget —
 * whether to abort the migration or record the failure and continue. Process
 * exceptions therefore extend {@see \RuntimeException}, not {@see \LogicException}.
 *
 * Stable error codes are enumerated in {@see self::CODES}. New process plugins
 * that surface new failure modes MUST append their code here before raising it,
 * so dashboards and tests can pattern-match exhaustively.
 *
 * Note: the integer `$code` constructor argument of {@see \Exception} is left
 * at 0; semantics live in the public readonly {@see $code} property (string).
 *
 * @api
 *
 * @spec FR-045 — typed exception surface (process layer)
 */
final class ProcessException extends \RuntimeException
{
    /**
     * `LookupProcessor` could not resolve a source id against the named
     * sibling migration's id-map.
     */
    public const string CODE_LOOKUP_MISS = 'LOOKUP_MISS';

    /**
     * `TypeCoerceProcessor` could not coerce the incoming value to the
     * declared target scalar type.
     */
    public const string CODE_TYPE_COERCE_FAIL = 'TYPE_COERCE_FAIL';

    /**
     * Every stable error code shipped by the framework's process plugins.
     *
     * New entries are append-only — third-party process plugins SHOULD NOT
     * reuse a framework code with different semantics.
     *
     * @var list<string>
     */
    public const array CODES = [
        self::CODE_LOOKUP_MISS,
        self::CODE_TYPE_COERCE_FAIL,
    ];

    /**
     * @param string $processCode One of {@see self::CODES} (or a future entry). Named `$processCode` (not `$code`) so it does not collide with the read-write integer `\Exception::$code` property — the framework-level semantic code is always a string, kept on a dedicated readonly property.
     * @param string $sourceField The source-field name whose value failed transformation. Non-empty.
     * @param string $migrationId Id of the running migration. Non-empty.
     * @param string $message Human-readable explanation.
     * @param \Throwable|null $previous Underlying cause, if any.
     *
     * @throws \InvalidArgumentException If $processCode, $sourceField, or $migrationId is empty.
     */
    public function __construct(
        public readonly string $processCode,
        public readonly string $sourceField,
        public readonly string $migrationId,
        string $message,
        ?\Throwable $previous = null,
    ) {
        if ($processCode === '') {
            throw new \InvalidArgumentException('ProcessException::$processCode must be a non-empty string.');
        }
        if ($sourceField === '') {
            throw new \InvalidArgumentException('ProcessException::$sourceField must be a non-empty string.');
        }
        if ($migrationId === '') {
            throw new \InvalidArgumentException('ProcessException::$migrationId must be a non-empty string.');
        }

        parent::__construct($message, 0, $previous);
    }
}
