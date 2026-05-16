<?php

declare(strict_types=1);

namespace Waaseyaa\Listing;

use Waaseyaa\Listing\Exception\UnknownListingException;

/**
 * Read-only registry of {@see ListingDefinition} instances keyed by id.
 *
 * Populated at boot time from the output of {@see ListingDiscoverer}, this
 * registry is the canonical lookup surface for the {@code ListingResolver}
 * (WP05) and any consumer that needs to resolve a listing id to its
 * definition (e.g. routing controllers, CLI commands).
 *
 * The constructor accepts an id-keyed map so callers can supply already-
 * indexed input; {@see self::fromList()} is provided as a convenience for
 * callers that hold a flat {@code list<ListingDefinition>} (typically the
 * direct output of {@see ListingDiscoverer::discover()}).
 *
 * @api
 */
final class ListingDefinitionRegistry
{
    /**
     * @param array<non-empty-string, ListingDefinition> $byId Id-keyed
     *        map of definitions. Keys must equal the corresponding
     *        {@code $definition->id}; callers should use
     *        {@see self::fromList()} to avoid hand-building the map.
     */
    public function __construct(private readonly array $byId) {}

    /**
     * Build a registry from a flat list, typically from
     * {@see ListingDiscoverer::discover()}.
     *
     * The discoverer already guarantees id uniqueness across providers
     * (FR-016), so this method does not re-check for duplicates — passing
     * a list with duplicate ids will silently retain the last occurrence.
     *
     * @param list<ListingDefinition> $definitions
     */
    public static function fromList(array $definitions): self
    {
        /** @var array<non-empty-string, ListingDefinition> $byId */
        $byId = [];
        foreach ($definitions as $definition) {
            $byId[$definition->id] = $definition;
        }

        return new self($byId);
    }

    /**
     * Resolve a listing id to its definition.
     *
     * @throws UnknownListingException If {@code $id} is not registered
     *                                 (FR-017).
     */
    public function get(string $id): ListingDefinition
    {
        return $this->byId[$id] ?? throw new UnknownListingException($id);
    }

    /**
     * Whether a listing with {@code $id} is registered.
     */
    public function has(string $id): bool
    {
        return isset($this->byId[$id]);
    }

    /**
     * All registered definitions, id-keyed.
     *
     * @return array<non-empty-string, ListingDefinition>
     */
    public function all(): array
    {
        return $this->byId;
    }
}
