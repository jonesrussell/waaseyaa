<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Exception;

/**
 * Thrown when a request body exceeds the configured maximum size.
 */
final class PayloadTooLargeException extends \DomainException
{
    public function __construct(int $maxBytes)
    {
        parent::__construct("Payload exceeds maximum {$maxBytes} bytes.");
    }
}
