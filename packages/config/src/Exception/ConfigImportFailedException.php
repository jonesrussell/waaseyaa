<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Exception;

/**
 * Raised when a single sync entry fails to apply during `config:import`.
 *
 * Carries the entity ref, a stable error-code string, and the originating
 * cause (when present). The importer catches this per-entity, records the
 * failure, and either continues (default) or halts (`--halt-on-error`).
 *
 * Stable error-code string: `'config.import.failed'` by default. Callers may
 * supply a sub-code (e.g. `'config.import.validation_failed'`,
 * `'config.import.apply_failed'`) via the `$errorCode` constructor parameter.
 *
 * Stability scope (charter §5.5): the exception FQCN, the `errorCode`,
 * `ref`, and the constructor signature are on stable surface. The integer
 * `\Exception::$code` is always `0` — consume `errorCode` for routing.
 *
 * @api
 */
final class ConfigImportFailedException extends \RuntimeException
{
    public const CODE_APPLY_FAILED = 'config.import.apply_failed';
    public const CODE_VALIDATION_FAILED = 'config.import.validation_failed';
    public const CODE_TRANSACTION_FAILED = 'config.import.transaction_failed';

    /** Stable error-code string (companion to the inherited integer `$code`). */
    public readonly string $errorCode;

    /**
     * @param string          $ref       `<entity_type>.<entity_id>` ref of the failing entity.
     * @param string          $reason    Human-readable failure reason; surfaces to the CLI line.
     * @param string          $errorCode Stable error-code string. Defaults to `'config.import.failed'`.
     * @param ?\Throwable     $previous  The originating cause, if any.
     */
    public function __construct(
        public readonly string $ref,
        string $reason,
        string $errorCode = 'config.import.failed',
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = $errorCode;
        parent::__construct(
            sprintf('Config import failed for "%s": %s', $ref, $reason),
            0,
            $previous,
        );
    }

    public static function applyFailed(string $ref, string $reason, ?\Throwable $previous = null): self
    {
        return new self($ref, $reason, self::CODE_APPLY_FAILED, $previous);
    }

    public static function transactionFailed(string $ref, string $reason, ?\Throwable $previous = null): self
    {
        return new self($ref, $reason, self::CODE_TRANSACTION_FAILED, $previous);
    }

    public static function validationFailed(string $ref, string $reason, ?\Throwable $previous = null): self
    {
        return new self($ref, $reason, self::CODE_VALIDATION_FAILED, $previous);
    }
}
