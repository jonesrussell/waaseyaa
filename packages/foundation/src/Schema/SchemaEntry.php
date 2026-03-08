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
}
