<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Exception;

/**
 * Thrown when {@see \Waaseyaa\Migration\Discovery\MigrationRegistry::boot()}
 * detects a cycle in the registered migrations' dependency graph (FR-015).
 *
 * The exception carries the offending cycle path with the first id repeated
 * at the end so log readers can immediately see the loop, e.g.
 * `['wp_posts_to_teachings', 'wp_users_to_accounts', 'wp_posts_to_teachings']`.
 *
 * The framework refuses to boot when this is raised — there is no safe
 * execution order for a cyclic DAG.
 *
 * @api
 */
final class MigrationCycleException extends \LogicException
{
    /**
     * @param list<string> $cyclePath Ordered migration ids forming the cycle, with the first id repeated at the end (length >= 2). Example: `['a', 'b', 'a']`.
     *
     * @throws \InvalidArgumentException When $cyclePath is shorter than two entries or the first and last ids do not match.
     */
    public function __construct(
        public readonly array $cyclePath,
    ) {
        if (\count($cyclePath) < 2) {
            throw new \InvalidArgumentException(\sprintf(
                'MigrationCycleException::$cyclePath must contain at least two entries (start and closing repeat); got %d.',
                \count($cyclePath),
            ));
        }
        if ($cyclePath[0] !== $cyclePath[\array_key_last($cyclePath)]) {
            throw new \InvalidArgumentException(\sprintf(
                'MigrationCycleException::$cyclePath must close on itself; first id %s does not equal last id %s.',
                \var_export($cyclePath[0], true),
                \var_export($cyclePath[\array_key_last($cyclePath)], true),
            ));
        }

        parent::__construct(\sprintf(
            'Migration dependency cycle detected: %s.',
            \implode(' -> ', $cyclePath),
        ), code: 0);
    }
}
