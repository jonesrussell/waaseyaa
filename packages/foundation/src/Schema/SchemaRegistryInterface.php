<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema;

interface SchemaRegistryInterface
{
    /** @return list<SchemaEntry> Schemas sorted by entity type ID */
    public function list(): array;

    public function get(string $id): ?SchemaEntry;
}
