<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Provider;

/**
 * @api
 */
final class RateLimitException extends ProviderException
{
    public function __construct(
        public readonly int $retryAfterSeconds,
        string $message = '',
    ) {
        parent::__construct($message ?: "Rate limited. Retry after {$retryAfterSeconds} seconds.");
    }
}
