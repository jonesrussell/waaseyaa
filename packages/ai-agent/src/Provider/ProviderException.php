<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Provider;

/**
 * Base class for all AI provider errors.
 *
 * Subclasses carry retry semantics:
 * - {@see TransportException} — transient (5xx / network) — retryable
 * - {@see RateLimitException} — 429 rate limit — retryable with backoff
 * - {@see ClientErrorException} — 4xx non-429 — non-retryable
 *
 * @api
 */
abstract class ProviderException extends \RuntimeException {}
