<?php

declare(strict_types=1);

namespace Aurora\Routing;

use Symfony\Component\Routing\Route;

/**
 * Value object holding the result of matching a route.
 */
final readonly class RouteMatch
{
    /**
     * @param string $routeName The matched route name.
     * @param Route $route The matched Symfony Route object.
     * @param array<string, mixed> $parameters The matched route parameters.
     */
    public function __construct(
        public string $routeName,
        public Route $route,
        public array $parameters = [],
    ) {}

    /**
     * Get a single parameter value by name.
     */
    public function getParameter(string $name): mixed
    {
        return $this->parameters[$name] ?? null;
    }

    /**
     * Check whether a parameter exists.
     */
    public function hasParameter(string $name): bool
    {
        return array_key_exists($name, $this->parameters);
    }
}
