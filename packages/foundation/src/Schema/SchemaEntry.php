<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema;

/**
 * @api
 */
final readonly class SchemaEntry
{
    public function __construct(
        public string $id,
        public string $version,
        public string $compatibility,
        public string $schemaPath,
        public string $schemaKind = 'entity',
        public string $stability = 'stable',
    ) {}

    /** @return array{id: string, version: string, compatibility: string, schema_path: string, schema_kind: string, stability: string} */
    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'version'       => $this->version,
            'compatibility' => $this->compatibility,
            'schema_path'   => $this->schemaPath,
            'schema_kind'   => $this->schemaKind,
            'stability'     => $this->stability,
        ];
    }
}
