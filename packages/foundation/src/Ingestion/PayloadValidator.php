<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Ingestion;

use Waaseyaa\Foundation\Schema\SchemaRegistryInterface;

/**
 * Validates ingestion payload data against the content-type schema from the registry.
 *
 * Loads the JSON Schema for the envelope's declared type, then checks:
 *   - required fields are present and non-empty
 *   - property types match the schema declaration
 *   - minLength / maxLength constraints
 *   - no unknown fields when additionalProperties is false
 *   - readOnly fields are rejected (they should not appear in ingestion payloads)
 */
final class PayloadValidator
{
    public function __construct(
        private readonly SchemaRegistryInterface $registry,
    ) {}

    /**
     * Validate an envelope's payload against the schema for its declared type.
     *
     * @return list<array{field: string, message: string}> Empty if valid.
     */
    public function validate(Envelope $envelope): array
    {
        $entry = $this->registry->get($envelope->type);

        if ($entry === null) {
            return [[
                'field'   => 'type',
                'message' => "No schema registered for type '{$envelope->type}'.",
            ]];
        }

        $schema = $this->loadSchema($entry->schemaPath);

        if ($schema === null) {
            return [[
                'field'   => 'type',
                'message' => "Failed to load schema for type '{$envelope->type}'.",
            ]];
        }

        return $this->validatePayload($envelope->payload, $schema);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadSchema(string $path): ?array
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $schema
     * @return list<array{field: string, message: string}>
     */
    private function validatePayload(array $payload, array $schema): array
    {
        $errors = [];
        /** @var array<string, array<string, mixed>> $properties */
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];
        $additionalProperties = $schema['additionalProperties'] ?? true;

        // Reject readOnly fields — they should not appear in ingestion payloads.
        foreach ($properties as $name => $propSchema) {
            if (($propSchema['readOnly'] ?? false) === true && array_key_exists($name, $payload)) {
                $errors[] = [
                    'field'   => $name,
                    'message' => "Field '{$name}' is read-only and must not be included in ingestion payloads.",
                ];
            }
        }

        // Check required fields (skip readOnly — they can't be submitted).
        foreach ($required as $field) {
            $fieldSchema = $properties[$field] ?? [];
            if (($fieldSchema['readOnly'] ?? false) === true) {
                continue;
            }

            if (!array_key_exists($field, $payload)) {
                $errors[] = [
                    'field'   => $field,
                    'message' => "Required field '{$field}' is missing.",
                ];
                continue;
            }

            // For string fields with minLength, check non-empty.
            if (($fieldSchema['type'] ?? null) === 'string'
                && isset($fieldSchema['minLength'])
                && is_string($payload[$field])
                && strlen($payload[$field]) < (int) $fieldSchema['minLength']
            ) {
                $errors[] = [
                    'field'   => $field,
                    'message' => "Field '{$field}' must have at least {$fieldSchema['minLength']} character(s).",
                ];
            }
        }

        // Type-check all provided fields.
        foreach ($payload as $name => $value) {
            $propSchema = $properties[$name] ?? null;

            // Unknown field check.
            if ($propSchema === null) {
                if ($additionalProperties === false) {
                    $errors[] = [
                        'field'   => $name,
                        'message' => "Unknown field '{$name}'. Not defined in schema.",
                    ];
                }
                continue;
            }

            // Skip further checks on readOnly (already reported above).
            if (($propSchema['readOnly'] ?? false) === true) {
                continue;
            }

            $this->validateFieldType($name, $value, $propSchema, $errors);
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $propSchema
     * @param list<array{field: string, message: string}> $errors
     */
    private function validateFieldType(string $name, mixed $value, array $propSchema, array &$errors): void
    {
        $expectedType = $propSchema['type'] ?? null;

        if ($expectedType === null) {
            return;
        }

        $typeValid = match ($expectedType) {
            'string'  => is_string($value),
            'integer' => is_int($value),
            'number'  => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'array'   => is_array($value) && array_is_list($value),
            'object'  => is_array($value) && !array_is_list($value),
            default   => true,
        };

        if (!$typeValid) {
            $errors[] = [
                'field'   => $name,
                'message' => "Field '{$name}' must be of type '{$expectedType}'.",
            ];
            return;
        }

        // String constraints.
        if ($expectedType === 'string' && is_string($value)) {
            if (isset($propSchema['minLength']) && strlen($value) < (int) $propSchema['minLength']) {
                $errors[] = [
                    'field'   => $name,
                    'message' => "Field '{$name}' must have at least {$propSchema['minLength']} character(s).",
                ];
            }

            if (isset($propSchema['maxLength']) && strlen($value) > (int) $propSchema['maxLength']) {
                $errors[] = [
                    'field'   => $name,
                    'message' => "Field '{$name}' must have at most {$propSchema['maxLength']} character(s).",
                ];
            }
        }
    }
}
