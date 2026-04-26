<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Attribute;

/**
 * Reads {@see ContentEntityType} from a concrete entity class (including parent classes).
 */
final class ContentEntityTypeReader
{
    /**
     * @param class-string $class
     */
    public static function entityTypeIdForClass(string $class): ?string
    {
        return EntityMetadataReader::forClass($class)->typeId;
    }
}
