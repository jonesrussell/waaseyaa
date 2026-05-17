<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Sync;

use Waaseyaa\Config\Exception\ConfigSerializationException;

/**
 * Maps entity field values to/from their canonical YAML representation per the
 * sync-store field-value mapping table (spec §5.3 / contracts/config-manifest.md).
 *
 * The mapping is **field-type-driven**, not entity-class-driven and not
 * backend-driven. The `FieldDefinition::getType()` string determines the YAML
 * shape; the active-store backend (sql-blob, sql-column, etc.) is irrelevant
 * at this layer.
 *
 * To keep `waaseyaa/config` free of an upward dependency on `waaseyaa/field`,
 * this mapper takes the field-type *string* and value directly rather than
 * a `FieldDefinition` object. WP03's `ConfigExporter` is the layer that
 * resolves a `FieldDefinition` to its type-string before calling here.
 *
 * Type table (FR-011a):
 *
 * | type              | YAML representation                       |
 * |-------------------|-------------------------------------------|
 * | string            | scalar string                             |
 * | int               | scalar int                                |
 * | bool              | scalar bool                               |
 * | datetime          | ISO 8601 string                           |
 * | json              | mapping / sequence (native YAML)          |
 * | text              | scalar string (block scalar if multiline) |
 * | uuid              | scalar string                             |
 * | entity_reference  | `<entity_type>.<entity_id>` string        |
 * | field_list        | sequence of scalars                       |
 *
 * Stability scope (charter §5.5): the table above is stable surface for v1.x.
 * Adding new types is additive; removing or changing the YAML shape for an
 * existing type requires a §4 deprecation cycle.
 *
 * @api
 */
final class FieldValueMapper
{
    public const TYPE_STRING = 'string';
    public const TYPE_INT = 'int';
    public const TYPE_BOOL = 'bool';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_JSON = 'json';
    public const TYPE_TEXT = 'text';
    public const TYPE_UUID = 'uuid';
    public const TYPE_ENTITY_REFERENCE = 'entity_reference';
    public const TYPE_FIELD_LIST = 'field_list';

    /**
     * Map an entity field value to its YAML-native representation.
     *
     * @throws ConfigSerializationException when $value cannot be represented as $fieldType
     */
    public function toYamlValue(string $fieldType, string $fieldName, mixed $value): mixed
    {
        return match ($fieldType) {
            self::TYPE_STRING, self::TYPE_TEXT, self::TYPE_UUID => $this->coerceToString($fieldType, $fieldName, $value),
            self::TYPE_INT => $this->coerceToInt($fieldName, $value),
            self::TYPE_BOOL => $this->coerceToBool($fieldName, $value),
            self::TYPE_DATETIME => $this->coerceToDateTime($fieldName, $value),
            self::TYPE_JSON => $this->coerceToJson($fieldName, $value),
            self::TYPE_ENTITY_REFERENCE => $this->coerceToEntityReference($fieldName, $value),
            self::TYPE_FIELD_LIST => $this->coerceToFieldList($fieldName, $value),
            default => throw ConfigSerializationException::typeMismatch($fieldName, $fieldType, get_debug_type($value)),
        };
    }

    /**
     * Inverse: map a parsed YAML value back to an entity-shaped field value.
     *
     * @throws ConfigSerializationException on type mismatch
     */
    public function fromYamlValue(string $fieldType, string $fieldName, mixed $yamlValue): mixed
    {
        // The shape we read from YAML matches what we emit, so the inverse is
        // (today) a strict-typed identity with re-validation.
        return $this->toYamlValue($fieldType, $fieldName, $yamlValue);
    }

    /**
     * Round-trip a complete fields array: $fields[name] => $value, given a
     * map of $fieldTypes[name] => $type-string. Returns a new array with
     * keys sorted alphabetically.
     *
     * @param array<string, string> $fieldTypes
     * @param array<string, mixed>  $fields
     *
     * @return array<string, mixed>
     */
    public function mapFieldsToYaml(array $fieldTypes, array $fields): array
    {
        $mapped = [];
        foreach ($fields as $name => $value) {
            if ($name === '') {
                throw new \InvalidArgumentException('Field names must be non-empty strings.');
            }
            if (!\array_key_exists($name, $fieldTypes)) {
                throw ConfigSerializationException::strayField('<in-memory>', $name);
            }
            $mapped[$name] = $this->toYamlValue($fieldTypes[$name], $name, $value);
        }
        ksort($mapped, \SORT_STRING);

        return $mapped;
    }

    private function coerceToString(string $fieldType, string $fieldName, mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }
        throw ConfigSerializationException::typeMismatch($fieldName, $fieldType, get_debug_type($value));
    }

    private function coerceToInt(string $fieldName, mixed $value): int
    {
        if (\is_int($value)) {
            return $value;
        }
        if (\is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }
        throw ConfigSerializationException::typeMismatch($fieldName, self::TYPE_INT, get_debug_type($value));
    }

    private function coerceToBool(string $fieldName, mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }
        if ($value === 0 || $value === 1) {
            return (bool) $value;
        }
        if ($value === 'true' || $value === 'false') {
            return $value === 'true';
        }
        throw ConfigSerializationException::typeMismatch($fieldName, self::TYPE_BOOL, get_debug_type($value));
    }

    private function coerceToDateTime(string $fieldName, mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }
        if (!\is_string($value) || $value === '') {
            throw ConfigSerializationException::typeMismatch($fieldName, self::TYPE_DATETIME, get_debug_type($value));
        }
        // Round-trip via DateTimeImmutable to verify shape and normalise.
        try {
            $dt = new \DateTimeImmutable($value);
        } catch (\Throwable) {
            throw ConfigSerializationException::typeMismatch($fieldName, self::TYPE_DATETIME, get_debug_type($value));
        }

        return $dt->format(\DateTimeInterface::ATOM);
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private function coerceToJson(string $fieldName, mixed $value): array|null
    {
        if ($value === null) {
            return null;
        }
        if (!\is_array($value)) {
            throw ConfigSerializationException::typeMismatch($fieldName, self::TYPE_JSON, get_debug_type($value));
        }

        // Recursively sort associative keys for deterministic emit; lists
        // (numeric-indexed arrays) retain their order.
        return $this->sortJsonKeys($value);
    }

    private function coerceToEntityReference(string $fieldName, mixed $value): string
    {
        if (!\is_string($value) || preg_match(ConfigSyncFile::REF_PATTERN, $value) !== 1) {
            throw ConfigSerializationException::typeMismatch(
                $fieldName,
                self::TYPE_ENTITY_REFERENCE,
                get_debug_type($value),
            );
        }

        return $value;
    }

    /**
     * @return list<bool|float|int|string>
     */
    private function coerceToFieldList(string $fieldName, mixed $value): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw ConfigSerializationException::typeMismatch($fieldName, self::TYPE_FIELD_LIST, get_debug_type($value));
        }
        $result = [];
        foreach ($value as $item) {
            if (!\is_scalar($item)) {
                throw ConfigSerializationException::typeMismatch($fieldName, self::TYPE_FIELD_LIST, get_debug_type($item));
            }
            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param array<int|string, mixed> $value
     *
     * @return array<int|string, mixed>
     */
    private function sortJsonKeys(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(
                fn(mixed $item): mixed => \is_array($item) ? $this->sortJsonKeys($item) : $item,
                $value,
            );
        }

        ksort($value, \SORT_STRING);
        foreach ($value as $key => $item) {
            if (\is_array($item)) {
                $value[$key] = $this->sortJsonKeys($item);
            }
        }

        return $value;
    }
}
