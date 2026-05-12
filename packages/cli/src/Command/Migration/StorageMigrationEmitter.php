<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command\Migration;

use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\EntityStorage\Backend\TypeMapping;

/**
 * Resolves field definitions to SQL column types for a target platform.
 *
 * Consumes the {@see TypeMapping} introduced in WP05 (T029) and validates
 * that every field on the given entity type has a §8.2 column mapping before
 * any file is written.
 *
 * @api
 */
final class StorageMigrationEmitter
{
    /**
     * Supported backend targets for this command.
     *
     * @var list<string>
     */
    public const SUPPORTED_TARGETS = ['sql-column'];

    /**
     * Emit the column map for a given entity type and platform.
     *
     * @param EntityTypeInterface $entityType  The entity type to inspect.
     * @param string              $platform    Platform key — {@see TypeMapping::PLATFORM_SQLITE} etc.
     *
     * @return array<string, string>  Map of field name => SQL column type string.
     *
     * @throws UnmappedFieldTypeException When a field type has no §8.2 column mapping.
     *
     * @api
     */
    public function emitColumnMap(EntityTypeInterface $entityType, string $platform): array
    {
        $columns = [];

        foreach ($entityType->getFieldDefinitions() as $name => $definition) {
            $fieldType = $definition->getType();

            // float_vector_<n> types cannot be stored in sql-column — §8.2 explicitly excludes them.
            if (preg_match('/^float_vector_\d+$/', strtolower($fieldType))) {
                throw new UnmappedFieldTypeException(
                    fieldId: $name,
                    fieldType: $fieldType,
                );
            }

            try {
                $columnType = TypeMapping::columnTypeFor(
                    platform: $platform,
                    fieldType: $fieldType,
                    length: $definition->getSettings()['length'] ?? null,
                    precision: $definition->getSettings()['precision'] ?? null,
                    scale: $definition->getSettings()['scale'] ?? null,
                );
            } catch (\InvalidArgumentException $e) {
                throw new UnmappedFieldTypeException(
                    fieldId: $name,
                    fieldType: $fieldType,
                    previous: $e,
                );
            }

            $columns[$name] = $columnType;
        }

        return $columns;
    }
}
