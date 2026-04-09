<?php

declare(strict_types=1);

namespace Waaseyaa\Api;

/**
 * Applies JSON:API sparse fieldsets to a resource.
 *
 * @see https://jsonapi.org/format/#fetching-sparse-fieldsets
 */
final class SparseFieldsetApplicator
{
    /**
     * @param list<string> $allowedFields Field and relationship names to keep
     */
    public static function apply(JsonApiResource $resource, array $allowedFields): JsonApiResource
    {
        $mask = array_flip($allowedFields);

        return new JsonApiResource(
            type: $resource->type,
            id: $resource->id,
            attributes: array_intersect_key($resource->attributes, $mask),
            relationships: array_intersect_key($resource->relationships, $mask),
            links: $resource->links,
            meta: $resource->meta,
        );
    }
}
