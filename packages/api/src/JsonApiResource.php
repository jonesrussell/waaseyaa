<?php

declare(strict_types=1);

namespace Aurora\Api;

/**
 * Value object representing a JSON:API resource object.
 *
 * @see https://jsonapi.org/format/#document-resource-objects
 */
final readonly class JsonApiResource
{
    /**
     * @param string               $type          The resource type (entity type ID).
     * @param string               $id            The resource ID (entity UUID or ID as string).
     * @param array<string, mixed> $attributes    Resource attributes (entity data minus id/type/relationships).
     * @param array<string, mixed> $relationships Resource relationships (future use).
     * @param array<string, string> $links        Resource links (e.g., 'self').
     * @param array<string, mixed> $meta          Optional metadata.
     */
    public function __construct(
        public string $type,
        public string $id,
        public array $attributes = [],
        public array $relationships = [],
        public array $links = [],
        public array $meta = [],
    ) {}

    /**
     * Serialize this resource to a JSON:API-compliant array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $resource = [
            'type' => $this->type,
            'id' => $this->id,
        ];

        if ($this->attributes !== []) {
            $resource['attributes'] = $this->attributes;
        }

        if ($this->relationships !== []) {
            $resource['relationships'] = $this->relationships;
        }

        if ($this->links !== []) {
            $resource['links'] = $this->links;
        }

        if ($this->meta !== []) {
            $resource['meta'] = $this->meta;
        }

        return $resource;
    }
}
