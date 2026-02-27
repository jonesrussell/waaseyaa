<?php

declare(strict_types=1);

namespace Aurora\Routing\ParamConverter;

use Aurora\Entity\EntityTypeManagerInterface;
use Symfony\Component\Routing\Route;

/**
 * Converts route parameters to loaded entity objects.
 *
 * When a route declares entity-typed parameters via the 'parameters' option,
 * this converter loads the corresponding entities from storage and replaces
 * the raw ID values in the parameter array with entity objects.
 *
 * Route option format:
 *   $route->setOption('parameters', [
 *       'node' => ['type' => 'entity:node'],
 *   ]);
 */
final class EntityParamConverter
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    /**
     * Convert entity route parameters to loaded entity objects.
     *
     * Examines the route's 'parameters' option for entries with type 'entity:{entityTypeId}'.
     * For each matching parameter that exists in the parameters array, loads the entity
     * and replaces the raw value with the loaded entity object.
     *
     * @param array<string, mixed> $parameters The matched route parameters.
     * @param Route $route The matched route definition.
     * @return array<string, mixed> Parameters with entity IDs replaced by loaded entities.
     */
    public function convert(array $parameters, Route $route): array
    {
        $routeParams = $route->getOption('parameters') ?? [];

        foreach ($routeParams as $name => $config) {
            if (!isset($config['type']) || !str_starts_with($config['type'], 'entity:')) {
                continue;
            }

            $entityTypeId = substr($config['type'], 7); // after 'entity:'

            if (!isset($parameters[$name])) {
                continue;
            }

            $storage = $this->entityTypeManager->getStorage($entityTypeId);
            $entity = $storage->load($parameters[$name]);

            if ($entity !== null) {
                $parameters[$name] = $entity;
            }
        }

        return $parameters;
    }
}
