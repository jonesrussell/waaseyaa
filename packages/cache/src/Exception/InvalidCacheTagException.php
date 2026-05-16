<?php

declare(strict_types=1);

namespace Waaseyaa\Cache\Exception;

use Waaseyaa\Cache\TaggedCacheInterface;

/**
 * Thrown when a cache tag string does not match the strict tag regex
 * {@see TaggedCacheInterface::TAG_REGEX} (`^[a-z][a-z0-9_:.-]*$`).
 *
 * Carries the offending tag value verbatim — no normalisation is applied so
 * the caller can see exactly which input was rejected.
 *
 * @api
 */
final class InvalidCacheTagException extends \InvalidArgumentException
{
    public function __construct(public readonly string $invalidTag)
    {
        parent::__construct(\sprintf(
            'Cache tag %s does not match %s',
            var_export($invalidTag, true),
            TaggedCacheInterface::TAG_REGEX,
        ));
    }
}
