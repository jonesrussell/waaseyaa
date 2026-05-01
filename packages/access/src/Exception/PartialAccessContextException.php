<?php

declare(strict_types=1);

namespace Waaseyaa\Access\Exception;

/**
 * Thrown when a caller hands access-aware code an incomplete access context —
 * exactly one of `(EntityAccessHandler $handler, AccountInterface $account)`
 * is `null` and the other is non-null.
 *
 * The paired-nullable invariant is: both null (no access filtering) or both
 * non-null (filtering enabled). Mixed states silently degraded output before
 * mission #824 WP05; they now fail loudly.
 *
 * Caught and turned into a 500 by the controller pipeline if it ever reaches
 * runtime in production code; surfaced as a test failure during development.
 */
final class PartialAccessContextException extends \LogicException
{
    public static function forSerializer(string $callerMethod): self
    {
        return new self(sprintf(
            '[PARTIAL_ACCESS_CONTEXT] %s requires both $accessHandler and $account, '
                . 'or neither. Pass both to enable access filtering, or neither to skip it. '
                . 'Mixed nullability yields silently degraded output and is forbidden.',
            $callerMethod,
        ));
    }
}
