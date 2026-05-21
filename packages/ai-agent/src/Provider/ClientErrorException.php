<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Provider;

/**
 * Thrown for 4xx HTTP errors that are NOT rate-limit (429) responses.
 * Non-retryable: the error is in the request, not the provider's availability.
 *
 * @api
 */
final class ClientErrorException extends ProviderException {}
