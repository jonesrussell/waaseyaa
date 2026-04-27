<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Helper;

use Waaseyaa\Entity\EntityType;
use Waaseyaa\Field\FieldDefinitionInterface;

/**
 * Test-only escape hatch for building shape-only {@see EntityType} instances
 * without going through {@see EntityType::fromClass()} reflection.
 *
 * Stubs are intentionally NOT cached and do NOT pollute the fromClass() cache.
 *
 * @internal Test helper. Production code MUST NOT use this class.
 */
final class TestEntityType
{
    /**
     * Build a synthetic {@see EntityType} for tests.
     *
     * @param array<string, FieldDefinitionInterface|array<string, mixed>> $fieldDefinitions
     * @param array<string, string> $keys
     */
    public static function stub(
        string $id,
        array $fieldDefinitions = [],
        array $keys = ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label'],
        ?string $class = null,
        ?string $label = null,
    ): EntityType {
        $class ??= self::syntheticClassName($id);
        $label ??= \ucfirst(\str_replace('_', ' ', $id));

        return new EntityType(
            id: $id,
            label: $label,
            class: $class,
            keys: $keys,
            _fieldDefinitions: $fieldDefinitions,
        );
    }

    private static function syntheticClassName(string $id): string
    {
        $studly = \str_replace([' ', '_'], '', \ucwords($id, '_'));

        return 'Waaseyaa\\Entity\\Tests\\Helper\\__StubEntities__\\' . $studly;
    }
}
