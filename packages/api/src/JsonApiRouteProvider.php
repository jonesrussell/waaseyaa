<?php

declare(strict_types=1);

namespace Aurora\Api;

use Aurora\Entity\EntityTypeManagerInterface;
use Aurora\Routing\AuroraRouter;
use Aurora\Routing\RouteBuilder;

/**
 * Automatically registers JSON:API routes for all known entity types.
 *
 * For each entity type registered with the EntityTypeManager, this provider
 * creates five routes:
 *
 *   GET    /api/{entityType}       — collection (index)
 *   GET    /api/{entityType}/{id}  — single resource (show)
 *   POST   /api/{entityType}       — create (store)
 *   PATCH  /api/{entityType}/{id}  — update
 *   DELETE /api/{entityType}/{id}  — delete
 */
final class JsonApiRouteProvider
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly string $basePath = '/api',
    ) {}

    /**
     * Register JSON:API routes for all entity types on the given router.
     */
    public function registerRoutes(AuroraRouter $router): void
    {
        foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $definition) {
            $this->registerEntityTypeRoutes($router, $entityTypeId);
        }
    }

    /**
     * Register the five CRUD routes for a single entity type.
     */
    private function registerEntityTypeRoutes(AuroraRouter $router, string $entityTypeId): void
    {
        $collectionPath = $this->basePath . '/' . $entityTypeId;
        $resourcePath = $collectionPath . '/{id}';

        // GET collection (index).
        $router->addRoute(
            "api.{$entityTypeId}.index",
            RouteBuilder::create($collectionPath)
                ->controller("Aurora\\Api\\JsonApiController::index")
                ->methods('GET')
                ->default('_entity_type', $entityTypeId)
                ->build(),
        );

        // GET single resource (show).
        $router->addRoute(
            "api.{$entityTypeId}.show",
            RouteBuilder::create($resourcePath)
                ->controller("Aurora\\Api\\JsonApiController::show")
                ->methods('GET')
                ->default('_entity_type', $entityTypeId)
                ->build(),
        );

        // POST create (store).
        $router->addRoute(
            "api.{$entityTypeId}.store",
            RouteBuilder::create($collectionPath)
                ->controller("Aurora\\Api\\JsonApiController::store")
                ->methods('POST')
                ->default('_entity_type', $entityTypeId)
                ->build(),
        );

        // PATCH update.
        $router->addRoute(
            "api.{$entityTypeId}.update",
            RouteBuilder::create($resourcePath)
                ->controller("Aurora\\Api\\JsonApiController::update")
                ->methods('PATCH')
                ->default('_entity_type', $entityTypeId)
                ->build(),
        );

        // DELETE.
        $router->addRoute(
            "api.{$entityTypeId}.destroy",
            RouteBuilder::create($resourcePath)
                ->controller("Aurora\\Api\\JsonApiController::destroy")
                ->methods('DELETE')
                ->default('_entity_type', $entityTypeId)
                ->build(),
        );
    }
}
