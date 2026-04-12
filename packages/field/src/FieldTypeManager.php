<?php

declare(strict_types=1);

namespace Waaseyaa\Field;

use Waaseyaa\Cache\CacheBackendInterface;
use Waaseyaa\Field\Attribute\FieldType;
use Waaseyaa\Plugin\DefaultPluginManager;
use Waaseyaa\Plugin\Discovery\AttributeDiscovery;

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
}
