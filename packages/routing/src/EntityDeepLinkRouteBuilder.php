<?php

declare(strict_types=1);

namespace Waaseyaa\Routing;

/**
 * Fluent helper for building entity deep-link routes.
 *
 * Composes {@see RouteBuilder} to produce a GET route at
 * `{segment}/{entityTypeId}/{id}` with the entity parameter
 * automatically wired for upcasting via {@see ParamConverter\EntityParamConverter}.
 *
 * Usage:
 *   $route = EntityDeepLinkRouteBuilder::for('/edit', 'node')
 *       ->controller('App\Controller\NodeController::edit')
 *       ->build();
 *
 *   // Produces: GET /edit/node/{id}
 *   // with parameters option: ['id' => ['type' => 'entity:node']]
 */
final readonly class EntityDeepLinkRouteBuilder
{
    public function __construct(
        public string $segment,
        public string $entityTypeId,
    ) {}

    /**
     * Create a builder for the given URL segment and entity type.
     *
     * @param string $segment The URL prefix, e.g. '/edit'. Trailing slashes are trimmed.
     * @param string $entityTypeId The entity type ID, e.g. 'node'.
     */
    public static function for(string $segment, string $entityTypeId): self
    {
        return new self($segment, $entityTypeId);
    }

    /**
     * Attach a controller and return a fully-configured {@see RouteBuilder}.
     *
     * The returned builder is configured with:
     *   - Path: `{segment}/{entityTypeId}/{id}` (trailing slash on segment is trimmed)
     *   - Method: GET
     *   - Entity parameter `id` resolved against the registered entity type
     *
     * The returned builder is fully chainable — callers may add additional
     * methods, requirements, and access options before calling {@see RouteBuilder::build()}.
     *
     * @param string|callable $controller The controller reference.
     */
    public function controller(string|callable $controller): RouteBuilder
    {
        $path = rtrim($this->segment, '/') . '/' . $this->entityTypeId . '/{id}';

        return RouteBuilder::create($path)
            ->controller($controller)
            ->entityParameter('id', $this->entityTypeId)
            ->methods('GET');
    }
}
