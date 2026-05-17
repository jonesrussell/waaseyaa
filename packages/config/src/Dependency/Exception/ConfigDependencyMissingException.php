<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Dependency\Exception;

/**
 * Raised when a declared config dependency cannot be located in either the
 * sync store or the active store.
 *
 * Stable error-code string: `'config.dependency.missing'`, exposed via
 * `$exception->errorCode` and `$exception->code` (alias). The inherited
 * `\Exception::$code` is an integer that mirrors PHP convention (always
 * `0` here); the framework-stable string code is `errorCode`.
 *
 * Stability scope (charter §5.5): this exception type, its `errorCode` /
 * `code` accessors, and `missingRef` / `requiredBy` properties are on the
 * stable surface.
 *
 * @api
 */
final class ConfigDependencyMissingException extends \RuntimeException
{
    /** Stable error-code string (also exposed via the readonly `$code` virtual property). */
    public readonly string $errorCode;

    /**
     * @param string $missingRef The `<entity_type>.<entity_id>` ref that does
     *                           not exist in either store.
     * @param string $requiredBy The `<entity_type>.<entity_id>` ref of the
     *                           entity whose dependency declaration cited
     *                           `$missingRef`.
     * @param string $errorCode  Stable error-code string. Defaults to
     *                           `'config.dependency.missing'`.
     */
    public function __construct(
        public readonly string $missingRef,
        public readonly string $requiredBy,
        string $errorCode = 'config.dependency.missing',
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = $errorCode;
        parent::__construct(
            \sprintf(
                "Config dependency '%s' required by '%s' is not present in sync store or active store.",
                $missingRef,
                $requiredBy,
            ),
            0,
            $previous,
        );
    }

    /**
     * Magic accessor exposing `errorCode` under the framework-stable name
     * `code`. The shadowed integer `\Exception::$code` is reachable via
     * `getCode()` which always returns `0` for this exception.
     */
    public function __get(string $name): string
    {
        if ($name === 'code') {
            return $this->errorCode;
        }

        throw new \OutOfBoundsException(\sprintf(
            'Property "%s" is not defined on %s.',
            $name,
            self::class,
        ));
    }

    public function __isset(string $name): bool
    {
        return $name === 'code';
    }
}
