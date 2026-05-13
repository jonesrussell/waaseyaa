<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Exception;

/**
 * Thrown when entity configuration is invalid in a way that prevents safe
 * runtime behaviour (as opposed to {@see InvalidEntityTypeException}, which
 * specifically targets entity-type definitions).
 *
 * Examples: a fallback-chain function that returns more entries than the
 * configured bound (FR-038 / NFR-002), or a translatable-field configuration
 * inconsistent with its entity type.
 *
 * @api WP06 — surfaced by FallbackChainResolver and downstream configuration validators.
 */
final class InvalidConfigurationException extends \RuntimeException
{
    /**
     * Build an exception describing a fallback chain that exceeds the bound.
     *
     * @param int $actual the number of entries the chain function returned
     * @param int $max    the configured maximum (NFR-002 default = 8)
     */
    public static function fallbackChainTooLong(int $actual, int $max): self
    {
        return new self(\sprintf(
            'Fallback chain length %d exceeds maximum %d. Configure translation.fallback_chain to return at most %d entries.',
            $actual,
            $max,
            $max,
        ));
    }
}
