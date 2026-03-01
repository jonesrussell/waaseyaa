<?php

declare(strict_types=1);

namespace Waaseyaa\Routing;

use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Waaseyaa router wrapping Symfony's routing components.
 *
 * Provides route matching (URL -> parameters) and URL generation
 * (route name + parameters -> URL).
 */
final class WaaseyaaRouter
{
    private RouteCollection $routes;
    private ?UrlMatcher $matcher;
    private ?UrlGenerator $generator;
    private RequestContext $context;

    public function __construct(?RequestContext $context = null)
    {
        $this->routes = new RouteCollection();
        $this->context = $context ?? new RequestContext();
        $this->matcher = null;
        $this->generator = null;
    }

    /**
     * Add a named route to the collection.
     *
     * Invalidates any cached matcher/generator so they are rebuilt on next use.
     */
    public function addRoute(string $name, Route $route): void
    {
        $this->routes->add($name, $route);
        // Reset matchers/generators when routes change.
        $this->matcher = null;
        $this->generator = null;
    }

    /**
     * Match a path to a route and return the parameters.
     *
     * The returned array always includes a '_route' key with the matched route name.
     *
     * @param string $pathinfo The path to match (e.g. "/node/42").
     * @return array<string, mixed> The matched parameters.
     *
     * @throws \Symfony\Component\Routing\Exception\ResourceNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MethodNotAllowedException
     */
    public function match(string $pathinfo): array
    {
        if ($this->matcher === null) {
            $this->matcher = new UrlMatcher($this->routes, $this->context);
        }

        return $this->matcher->match($pathinfo);
    }

    /**
     * Generate a URL for a named route.
     *
     * @param string $name The route name.
     * @param array<string, mixed> $parameters Parameters to substitute into the route pattern.
     * @return string The generated URL.
     *
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function generate(string $name, array $parameters = []): string
    {
        if ($this->generator === null) {
            $this->generator = new UrlGenerator($this->routes, $this->context);
        }

        return $this->generator->generate($name, $parameters);
    }

    /**
     * Return the underlying route collection.
     */
    public function getRouteCollection(): RouteCollection
    {
        return clone $this->routes;
    }
}
