<?php

declare(strict_types=1);

namespace Aurora\Config\Schema;

/**
 * Validates config data against JSON-Schema-like definitions.
 *
 * Schemas follow a subset of JSON Schema with the following supported keywords:
 * - type: string, integer, number, boolean, array, object
 * - properties: nested property schemas (for type: object)
 * - required: list of required property names
 * - enum: allowed values
 * - minimum / maximum: numeric range constraints
 * - default: default value (missing values with defaults are not violations)
 * - translatable: marker for translation-eligible keys (informational, not validated)
 */
final class ConfigSchemaValidator
{
    /** @var array<string, array<string, mixed>> */
    private array $schemas = [];

    /**
     * Register a schema definition for a config name.
     *
     * @param string $configName The config name (e.g., "system.site").
     * @param array<string, mixed> $schema The schema definition.
     */
    public function registerSchema(string $configName, array $schema): void
    {
        $this->schemas[$configName] = $schema;
    }

    /**
     * Check if a schema is registered for the given config name.
     */
    public function hasSchema(string $configName): bool
    {
        return isset($this->schemas[$configName]);
    }

    /**
     * Get the registered schema for a config name, or null if none.
     *
     * @return array<string, mixed>|null
     */
    public function getSchema(string $configName): ?array
    {
        return $this->schemas[$configName] ?? null;
    }

    /**
     * Validate config data using the registered schema for the given config name.
     *
     * @param string $configName The config name to look up the schema.
     * @param array<string, mixed> $data The config data to validate.
     * @return SchemaViolation[]
     *
     * @throws \RuntimeException If no schema is registered for the config name.
     */
    public function validateConfig(string $configName, array $data): array
    {
        if (!$this->hasSchema($configName)) {
            throw new \RuntimeException(sprintf(
                'No schema registered for config "%s".',
                $configName,
            ));
        }

        return $this->validate($data, $this->schemas[$configName]);
    }

    /**
     * Validate config data against an explicit schema definition.
     *
     * @param array<string, mixed> $data The config data to validate.
     * @param array<string, mixed> $schema The schema to validate against.
     * @return SchemaViolation[]
     */
    public function validate(array $data, array $schema): array
    {
        return $this->validateValue($data, $schema, '');
    }

    /**
     * @return SchemaViolation[]
     */
    private function validateValue(mixed $value, array $schema, string $path): array
    {
        $violations = [];

        // Type checking
        if (isset($schema['type'])) {
            $typeViolation = $this->checkType($value, $schema['type'], $path);
            if ($typeViolation !== null) {
                $violations[] = $typeViolation;
                // If type is wrong, skip further checks on this value
                return $violations;
            }
        }

        // Enum checking
        if (isset($schema['enum']) && is_array($schema['enum'])) {
            if (!in_array($value, $schema['enum'], true)) {
                $violations[] = new SchemaViolation(
                    path: $path,
                    message: sprintf(
                        'Value must be one of the allowed enum values: [%s]. Got: %s.',
                        implode(', ', array_map(fn($v) => (string) $v, $schema['enum'])),
                        is_scalar($value) ? (string) $value : gettype($value),
                    ),
                );
            }
        }

        // Minimum checking (numeric)
        if (isset($schema['minimum']) && is_numeric($value)) {
            if ($value < $schema['minimum']) {
                $violations[] = new SchemaViolation(
                    path: $path,
                    message: sprintf(
                        'Value %s is less than minimum %s.',
                        (string) $value,
                        (string) $schema['minimum'],
                    ),
                );
            }
        }

        // Maximum checking (numeric)
        if (isset($schema['maximum']) && is_numeric($value)) {
            if ($value > $schema['maximum']) {
                $violations[] = new SchemaViolation(
                    path: $path,
                    message: sprintf(
                        'Value %s is greater than maximum %s.',
                        (string) $value,
                        (string) $schema['maximum'],
                    ),
                );
            }
        }

        // Object properties validation
        if (($schema['type'] ?? null) === 'object' && isset($schema['properties']) && is_array($value)) {
            $violations = array_merge(
                $violations,
                $this->validateObjectProperties($value, $schema, $path),
            );
        }

        return $violations;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $schema
     * @return SchemaViolation[]
     */
    private function validateObjectProperties(array $data, array $schema, string $path): array
    {
        $violations = [];
        $properties = $schema['properties'] ?? [];

        // Check required properties
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $requiredProp) {
                if (!array_key_exists($requiredProp, $data)) {
                    // Check if the property has a default
                    $propSchema = $properties[$requiredProp] ?? [];
                    if (!array_key_exists('default', $propSchema)) {
                        $violations[] = new SchemaViolation(
                            path: $this->joinPath($path, $requiredProp),
                            message: sprintf('Property "%s" is required but missing.', $requiredProp),
                        );
                    }
                }
            }
        }

        // Validate each property that exists in data and has a schema
        foreach ($properties as $propName => $propSchema) {
            if (!array_key_exists($propName, $data)) {
                // Property not present; skip (required check is above)
                continue;
            }

            $propPath = $this->joinPath($path, $propName);
            $violations = array_merge(
                $violations,
                $this->validateValue($data[$propName], $propSchema, $propPath),
            );
        }

        return $violations;
    }

    private function checkType(mixed $value, string $expectedType, string $path): ?SchemaViolation
    {
        $valid = match ($expectedType) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && (array_values($value) === $value || $value === []),
            'object' => is_array($value),
            default => true, // Unknown types pass
        };

        if (!$valid) {
            return new SchemaViolation(
                path: $path,
                message: sprintf(
                    'Expected type "%s", got "%s".',
                    $expectedType,
                    get_debug_type($value),
                ),
            );
        }

        return null;
    }

    private function joinPath(string $base, string $key): string
    {
        if ($base === '') {
            return $key;
        }

        return $base . '.' . $key;
    }
}
