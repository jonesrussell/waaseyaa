<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Dsl;

/**
 * @api
 */
final readonly class TaskDefinition
{
    public function __construct(
        public string $operation,
        public string $entityType,
        public ?string $field = null,
        public array $parameters = [],
    ) {}

    /** @return array{operation: string, entity_type: string, field: ?string, parameters: array<string, mixed>} */
    public function toArray(): array
    {
        return [
            'operation' => $this->operation,
            'entity_type' => $this->entityType,
            'field' => $this->field,
            'parameters' => $this->parameters,
        ];
    }
}
