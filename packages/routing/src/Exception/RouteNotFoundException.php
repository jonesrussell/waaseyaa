<?php

declare(strict_types=1);

namespace Waaseyaa\Routing\Exception;

/**
 * Thrown when no route matches the request path (replaces exposing
 * {@see \Symfony\Component\Routing\Exception\ResourceNotFoundException} to callers).
 */
final class RouteNotFoundException extends \RuntimeException
{
    public function __construct(
        public readonly string $path,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(sprintf('No route matches "%s".', $path), 0, $previous);
    }
}
