<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Provider;

/**
 * Thrown for transient provider errors: 5xx HTTP responses, connection
 * timeouts, and network-level failures. Retryable per the FR-025 budget.
 *
 * @api
 */
final class TransportException extends ProviderException {}
