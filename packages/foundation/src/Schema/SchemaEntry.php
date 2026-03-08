<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema;

final readonly class SchemaEntry
{
    public function __construct(
        public string $id,
        public string $version,
        public string $compatibility,
        public string $schemaPath,
    ) {}

    /** @return array{id: string, version: string, compatibility: string, schema_path: string} */
    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'version'       => $this->version,
            'compatibility' => $this->compatibility,
            'schema_path'   => $this->schemaPath,
        ];
    }
}
