<?php

declare(strict_types=1);

namespace Waaseyaa\Entity;

/**
 * Helpers for read paths that need cast-aware field values (#1181).
 *
 * {@see EntityInterface::toArray()} and storage remain storage-canonical; use this for API/SSR/GraphQL
 * and other presentation logic so {@see EntityBase::$casts} apply. Do not use for persistence snapshots.
 */
final class EntityValues
{
    /**
     * All keys from {@see EntityInterface::toArray()} with values from {@see EntityInterface::get()}.
     *
     * @return array<string, mixed>
     */
    public static function toCastAwareMap(EntityInterface $entity): array
    {
        $map = [];
        foreach (array_keys($entity->toArray()) as $name) {
            $key = (string) $name;
            $map[$key] = $entity->get($key);
        }

        return $map;
    }

    /**
     * Normalize status-like flags to 0/1 for strict comparisons (e.g. relationship published filters).
     */
    public static function statusToInt(mixed $status): int
    {
        if ($status instanceof \BackedEnum) {
            return self::statusToInt($status->value);
        }

        if ($status === true || $status === 1) {
            return 1;
        }

        if ($status === false || $status === 0 || $status === null) {
            return 0;
        }

        if (\is_string($status)) {
            $t = strtolower(trim($status));
            if (\in_array($t, ['1', 'true', 'published', 'yes'], true)) {
                return 1;
            }

            if (\in_array($t, ['0', 'false', 'no', ''], true)) {
                return 0;
            }
        }

        if (is_numeric($status)) {
            return ((int) $status) === 1 ? 1 : 0;
        }

        return 0;
    }
}
