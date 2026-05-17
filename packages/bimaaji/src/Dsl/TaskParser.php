<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Dsl;

/**
 * @api
 */
final class TaskParser
{
    public function parseJson(string $json): TaskDefinition
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException("Invalid JSON: {$e->getMessage()}", 0, $e);
        }

        return $this->parseArray($data);
    }

    /** @param array<string, mixed> $data */
    public function parseArray(array $data): TaskDefinition
    {
        if (!isset($data['operation'])) {
            throw new \InvalidArgumentException('Task definition requires "operation" field');
        }

        if (!isset($data['entity_type'])) {
            throw new \InvalidArgumentException('Task definition requires "entity_type" field');
        }

        return new TaskDefinition(
            operation: (string) $data['operation'],
            entityType: (string) $data['entity_type'],
            field: isset($data['field']) ? (string) $data['field'] : null,
            parameters: (array) ($data['parameters'] ?? []),
        );
    }
}
