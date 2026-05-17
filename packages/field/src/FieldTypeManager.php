<?php

declare(strict_types=1);

namespace Waaseyaa\Field;

use Waaseyaa\Cache\CacheBackendInterface;
use Waaseyaa\Field\Attribute\FieldType;
use Waaseyaa\Plugin\DefaultPluginManager;
use Waaseyaa\Plugin\Discovery\AttributeDiscovery;

/**
 * @api
 */
final class FieldTypeManager extends DefaultPluginManager implements FieldTypeManagerInterface
{
    /**
     * @param string[] $directories Directories to scan for field type plugins.
     */
    public function __construct(
        array $directories = [],
        ?CacheBackendInterface $cache = null,
    ) {
        $discovery = new AttributeDiscovery(
            directories: $directories,
            attributeClass: FieldType::class,
        );

        parent::__construct(
            discovery: $discovery,
            cache: $cache,
            cacheKey: 'field_type_definitions',
        );
    }

    public function getDefaultSettings(string $fieldType): array
    {
        $definition = $this->getDefinition($fieldType);
        $class = $definition->class;

        if (!is_subclass_of($class, FieldTypeInterface::class)) {
            return [];
        }

        return $class::defaultSettings();
    }

    public function getColumns(string $fieldType): array
    {
        $definition = $this->getDefinition($fieldType);
        $class = $definition->class;

        if (!is_subclass_of($class, FieldTypeInterface::class)) {
            return [];
        }

        return $class::schema();
    }

    /**
     * Resolve the JSON Schema fragment for a field definition by delegating
     * to the field type plugin's jsonSchemaFor() seam.
     */
    public function jsonSchemaFor(FieldDefinitionInterface $def): array
    {
        $class = $this->resolveItemClass($def->getType());

        if ($class === null) {
            // Unknown plugin: preserve legacy default emission.
            return ['type' => 'string'];
        }

        return $class::jsonSchemaFor($def);
    }

    /**
     * Resolve the storage column shape for a field definition by delegating
     * to the field type plugin's schemaFor() seam.
     *
     * @return array<string, array{type: string, description?: string}>
     */
    public function schemaFor(FieldDefinitionInterface $def): array
    {
        $class = $this->resolveItemClass($def->getType());

        if ($class === null) {
            return [];
        }

        return $class::schemaFor($def);
    }

    /**
     * @return class-string<FieldTypeInterface>|null
     */
    private function resolveItemClass(string $fieldType): ?string
    {
        if (!$this->hasDefinition($fieldType)) {
            return null;
        }

        $class = $this->getDefinition($fieldType)->class;

        if (!is_subclass_of($class, FieldTypeInterface::class)) {
            return null;
        }

        return $class;
    }
}
