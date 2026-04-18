<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Sync;

/**
 * Registry of NcHitToEntityMapperInterface instances.
 *
 * Apps register their concrete mappers here (typically from a service provider).
 * NcSyncService iterates all registered mappers for each hit and invokes the ones
 * whose supports() returns true.
 */
final class MapperRegistry
{
    /** @var list<NcHitToEntityMapperInterface> */
    private array $mappers = [];

    public function register(NcHitToEntityMapperInterface $mapper): void
    {
        $this->mappers[] = $mapper;
    }

    /**
     * Return mappers that support the given hit.
     *
     * @param array<string, mixed> $hit
     * @return list<NcHitToEntityMapperInterface>
     */
    public function mappersFor(array $hit): array
    {
        return array_values(array_filter(
            $this->mappers,
            static fn(NcHitToEntityMapperInterface $m): bool => $m->supports($hit),
        ));
    }

    /** @return list<NcHitToEntityMapperInterface> */
    public function all(): array
    {
        return $this->mappers;
    }

    public function count(): int
    {
        return count($this->mappers);
    }
}
