<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Exception;

/**
 * Thrown for storage-migration / two-axis schema failures during kernel boot,
 * schema sync, or migration generator runs.
 *
 * Surface is closed: construction is restricted to the named factory methods,
 * each carrying a stable string `$errorCode` (FR-040, FR-041).
 *
 * @see https://kitty-specs/entity-storage-translatable-revisions-01KRCDEE/contracts/exception-surface.md §4
 *
 * @api
 */
final class StorageMigrationException extends \RuntimeException
{
    public readonly string $errorCode;

    private function __construct(string $message, string $errorCode, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
    }

    /**
     * The migration generator was called with `--add-translations` or
     * `--add-revisions` against an entity type that is already two-axis
     * (revisionable + translatable). No-op promotions are blocked because
     * they would write empty migration files (FR-029).
     *
     * Stable error code: `'no_op_promotion'`.
     */
    public static function noOpPromotion(string $entityType): self
    {
        return new self(
            \sprintf(
                'Entity type "%s" is already two-axis (revisionable + translatable); no migration needed.',
                $entityType,
            ),
            'no_op_promotion',
        );
    }

    /**
     * Schema sync or kernel boot detected a field declared `translatable()`
     * on a backend that does not support translation × revision composition.
     * Only `sql-column` and `sql-blob` are allowed (FR-006).
     *
     * The message intentionally retains the literal token
     * `unsupportedTwoAxisField` so existing WP01/WP02 contract tests that
     * assert on the marker text continue to pass when the underlying
     * `\RuntimeException` is upgraded to this typed exception.
     *
     * Stable error code: `'unsupported_two_axis_field'`.
     */
    public static function unsupportedTwoAxisField(string $fieldName, string $backend): self
    {
        return new self(
            \sprintf(
                'unsupportedTwoAxisField: field "%s" uses backend "%s" which does not support '
                . 'translation × revision composition; allowed backends are sql-column and sql-blob.',
                $fieldName,
                $backend,
            ),
            'unsupported_two_axis_field',
        );
    }
}
