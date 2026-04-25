<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Attribute;

/**
 * Reads {@see ContentEntityType} from a concrete entity class.
 */
final class ContentEntityTypeReader
{
    /**
     * @param class-string $class
     */
    public static function entityTypeIdForClass(string $class): ?string
    {
        if (!class_exists($class)) {
            return null;
        }

        $ref = new \ReflectionClass($class);
        foreach ($ref->getAttributes(ContentEntityType::class) as $attr) {
            return $attr->newInstance()->id;
        }

        return null;
    }
}
