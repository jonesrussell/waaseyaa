<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Dependency\Exception;

/**
 * Raised when a config-dependency graph contains a cycle.
 *
 * The full cycle path is preserved on the exception (via {@see getCycle()})
 * for programmatic and test inspection. The human-readable message is
 * truncated at 5 hops for log and console readability — see
 * {@see buildMessage()}.
 *
 * Cycle path format: `[A, B, C, A]` — first and last entries are equal,
 * explicitly closing the cycle. Length is always >= 3.
 *
 * Stable error-code field: `'config.dependency.cycle'`, exposed via
 * `$exception->errorCode` and `$exception->code` (alias). The
 * inherited `\Exception::$code` is an integer that mirrors PHP convention
 * (always `0` here); the framework-stable string code is `errorCode`.
 *
 * Stability scope (charter §5.5): this exception type, its `errorCode` /
 * `code` accessors, and `cyclePath` / `getCycle()` are on the stable
 * surface.
 *
 * @api
 */
final class ConfigDependencyCycleException extends \RuntimeException
{
    /** Maximum hops rendered in the truncated message before inserting `…`. */
    public const MESSAGE_HOP_LIMIT = 5;

    /** Stable error-code string (also exposed via the readonly `$code` virtual property). */
    public readonly string $errorCode;

    /**
     * @param list<string> $cyclePath Full cycle path, closed (first == last).
     *                                Length must be >= 3 (A -> B -> A is the
     *                                minimum cycle, rendered as [A, B, A]).
     * @param string       $errorCode Stable error-code string. Defaults to
     *                                `'config.dependency.cycle'`.
     */
    public function __construct(
        public readonly array $cyclePath,
        string $errorCode = 'config.dependency.cycle',
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = $errorCode;
        parent::__construct(self::buildMessage($cyclePath), 0, $previous);
    }

    /**
     * Full cycle path, never truncated. First and last elements are equal.
     *
     * @return list<string>
     */
    public function getCycle(): array
    {
        return $this->cyclePath;
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

    /**
     * Render a human-readable cycle message, truncating at
     * {@see self::MESSAGE_HOP_LIMIT} hops with an ellipsis.
     *
     * @param list<string> $cyclePath
     */
    private static function buildMessage(array $cyclePath): string
    {
        if ($cyclePath === []) {
            // Defensive — should be unreachable; resolver always supplies a closed cycle.
            return 'Config dependency cycle: (empty)';
        }

        $count = \count($cyclePath);
        if ($count <= self::MESSAGE_HOP_LIMIT + 1) {
            $rendered = \implode(' → ', $cyclePath);
        } else {
            // Render the first MESSAGE_HOP_LIMIT entries, then `…`, then the
            // closing element (always equal to the first).
            $head = \array_slice($cyclePath, 0, self::MESSAGE_HOP_LIMIT);
            $tail = $cyclePath[$count - 1];
            $rendered = \implode(' → ', $head) . ' → … → ' . $tail;
        }

        return 'Config dependency cycle: ' . $rendered;
    }
}
