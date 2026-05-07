<?php

declare(strict_types=1);

namespace Waaseyaa\Routing\Exception;

/**
 * Thrown when a route exists for a path but the HTTP method is not allowed.
 */
final class RouteMethodNotAllowedException extends \RuntimeException
{
    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(
        public readonly array $allowedMethods,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : 'Method not allowed.', $code, $previous);
    }
}
