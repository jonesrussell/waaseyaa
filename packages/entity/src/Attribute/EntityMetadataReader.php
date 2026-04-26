<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Attribute;

use Waaseyaa\Entity\ContentEntityBase;

/**
 * Resolves {@see ContentEntityType} and {@see ContentEntityKeys} for a class with per-class caching.
 */
final class EntityMetadataReader
{
    /** @var array<string, EntityClassMetadata> */
    private static array $cache = [];

    /**
     * @param class-string $class
     */
    public static function forClass(string $class): EntityClassMetadata
    {
        if (isset(self::$cache[$class])) {
            return self::$cache[$class];
        }

        if (!class_exists($class)) {
            return self::$cache[$class] = new EntityClassMetadata(null, []);
        }

        $typeId = self::resolveTypeId($class);
        $keys = self::resolveKeys($class);

        return self::$cache[$class] = new EntityClassMetadata($typeId, $keys);
    }

    /**
     * @param class-string $class
     */
    public static function clearCacheForClass(string $class): void
    {
        unset(self::$cache[$class]);
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * @param class-string $class
     */
    private static function resolveTypeId(string $class): ?string
    {
        $ref = new \ReflectionClass($class);
        while (true) {
            foreach ($ref->getAttributes(ContentEntityType::class) as $attr) {
                return $attr->newInstance()->id;
            }
            $parent = $ref->getParentClass();
            if ($parent === false || $parent->getName() === \Waaseyaa\Entity\EntityBase::class) {
                break;
            }
            $ref = $parent;
        }

        return null;
    }

    /**
     * Walk from the first concrete ancestor below {@see ContentEntityBase} to $class; merge
     * {@see ContentEntityKeys} (later classes override), then apply identity defaults.
     *
     * @param class-string $class
     * @return array<string, string>
     */
    private static function resolveKeys(string $class): array
    {
        if (!is_subclass_of($class, ContentEntityBase::class)) {
            return [];
        }

        $chain = [];
        $r = new \ReflectionClass($class);
        while ($r->getName() !== ContentEntityBase::class) {
            $chain[] = $r;
            $parent = $r->getParentClass();
            if ($parent === false) {
                break;
            }
            $r = $parent;
        }

        $chain = array_reverse($chain);

        $keys = [];
        foreach ($chain as $ref) {
            foreach ($ref->getAttributes(ContentEntityKeys::class) as $a) {
                $i = $a->newInstance();
                if ($i->id !== null) {
                    $keys['id'] = $i->id;
                }
                if ($i->uuid !== null) {
                    $keys['uuid'] = $i->uuid;
                }
                if ($i->label !== null) {
                    $keys['label'] = $i->label;
                }
                if ($i->bundle !== null) {
                    $keys['bundle'] = $i->bundle;
                }
                if ($i->revision !== null) {
                    $keys['revision'] = $i->revision;
                }
                if ($i->langcode !== null) {
                    $keys['langcode'] = $i->langcode;
                }
            }
        }

        foreach (['id' => 'id', 'uuid' => 'uuid', 'label' => 'label'] as $logical => $storage) {
            if (!isset($keys[$logical])) {
                $keys[$logical] = $storage;
            }
        }

        $ordered = [];
        foreach (['id', 'uuid', 'label', 'bundle', 'revision', 'langcode'] as $logical) {
            if (\array_key_exists($logical, $keys)) {
                $ordered[$logical] = $keys[$logical];
            }
        }

        return $ordered;
    }
}
