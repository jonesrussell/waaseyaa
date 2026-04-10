<?php

declare(strict_types=1);

namespace Waaseyaa\TypedData\Coercion;

use BackedEnum;
use JsonException;

/**
 * Storage ↔ domain coercion for entity field `$casts` builtins: int, float, bool, string, array (JSON in storage).
 *
 * Consumed by `waaseyaa/entity` `ValueCaster` and by typed-data primitives' `getCastedValue()` (#1185).
 */
final class EntityCastCoercion
{
    public const CAST_INT = 'int';

    public const CAST_FLOAT = 'float';

    public const CAST_BOOL = 'bool';

    public const CAST_STRING = 'string';

    public const CAST_ARRAY = 'array';

    public static function castInInt(string $field, mixed $stored): int
    {
        if (is_int($stored)) {
            return $stored;
        }

        if (is_float($stored)) {
            return (int) $stored;
        }

        if (is_string($stored)) {
            if ($stored === '') {
                throw CoercionException::invalidStoredValue($field, self::CAST_INT, $stored, 'Empty string is not a valid integer.');
            }

            if (!is_numeric($stored)) {
                throw CoercionException::invalidStoredValue($field, self::CAST_INT, $stored, 'Value is not numeric.');
            }

            return (int) $stored;
        }

        if (is_bool($stored)) {
            return $stored ? 1 : 0;
        }

        throw CoercionException::invalidStoredValue($field, self::CAST_INT, $stored);
    }

    public static function castOutInt(string $field, mixed $domain): int
    {
        if (is_int($domain)) {
            return $domain;
        }

        if (is_float($domain)) {
            return (int) $domain;
        }

        if (is_string($domain) && is_numeric($domain)) {
            return (int) $domain;
        }

        if (is_bool($domain)) {
            return $domain ? 1 : 0;
        }

        throw CoercionException::invalidDomainValue($field, self::CAST_INT, $domain);
    }

    public static function castInFloat(string $field, mixed $stored): float
    {
        if (is_float($stored)) {
            return $stored;
        }

        if (is_int($stored)) {
            return (float) $stored;
        }

        if (is_string($stored)) {
            if ($stored === '') {
                throw CoercionException::invalidStoredValue($field, self::CAST_FLOAT, $stored, 'Empty string is not a valid float.');
            }

            if (!is_numeric($stored)) {
                throw CoercionException::invalidStoredValue($field, self::CAST_FLOAT, $stored, 'Value is not numeric.');
            }

            return (float) $stored;
        }

        if (is_bool($stored)) {
            return $stored ? 1.0 : 0.0;
        }

        throw CoercionException::invalidStoredValue($field, self::CAST_FLOAT, $stored);
    }

    public static function castOutFloat(string $field, mixed $domain): float
    {
        if (is_float($domain)) {
            return $domain;
        }

        if (is_int($domain)) {
            return (float) $domain;
        }

        if (is_string($domain) && is_numeric($domain)) {
            return (float) $domain;
        }

        if (is_bool($domain)) {
            return $domain ? 1.0 : 0.0;
        }

        throw CoercionException::invalidDomainValue($field, self::CAST_FLOAT, $domain);
    }

    public static function castInBool(string $field, mixed $stored): bool
    {
        if (is_bool($stored)) {
            return $stored;
        }

        if (is_int($stored)) {
            return $stored !== 0;
        }

        if (is_string($stored)) {
            $normalized = strtolower($stored);
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        throw CoercionException::invalidStoredValue($field, self::CAST_BOOL, $stored);
    }

    public static function castOutBool(string $field, mixed $domain): bool
    {
        if (is_bool($domain)) {
            return $domain;
        }

        if (is_int($domain)) {
            return $domain !== 0;
        }

        if (is_string($domain)) {
            $normalized = strtolower($domain);
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        throw CoercionException::invalidDomainValue($field, self::CAST_BOOL, $domain);
    }

    public static function castInString(string $field, mixed $stored): string
    {
        if (is_string($stored)) {
            return $stored;
        }

        if (is_scalar($stored)) {
            return (string) $stored;
        }

        throw CoercionException::invalidStoredValue(
            $field,
            self::CAST_STRING,
            $stored,
            'Only strings and scalars are supported.',
        );
    }

    public static function castOutString(string $field, mixed $domain): string
    {
        if (is_string($domain)) {
            return $domain;
        }

        if (is_scalar($domain)) {
            return (string) $domain;
        }

        if ($domain instanceof BackedEnum) {
            return (string) $domain->value;
        }

        throw CoercionException::invalidDomainValue($field, self::CAST_STRING, $domain);
    }

    /**
     * @return array<mixed>
     */
    public static function castInArray(string $field, mixed $stored): array
    {
        if (is_array($stored)) {
            return $stored;
        }

        if (!is_string($stored)) {
            throw CoercionException::invalidStoredValue($field, self::CAST_ARRAY, $stored);
        }

        if ($stored === '') {
            throw CoercionException::invalidStoredValue(
                $field,
                self::CAST_ARRAY,
                $stored,
                'Empty string is not valid JSON for an array cast.',
            );
        }

        try {
            $decoded = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw CoercionException::invalidStoredValue(
                $field,
                self::CAST_ARRAY,
                $stored,
                'Invalid JSON: ' . $e->getMessage(),
                $e,
            );
        }

        if (!is_array($decoded)) {
            throw CoercionException::invalidStoredValue(
                $field,
                self::CAST_ARRAY,
                $stored,
                'JSON must decode to an array.',
            );
        }

        return $decoded;
    }

    /**
     * @return non-falsy-string
     */
    public static function castOutArray(string $field, mixed $domain): string
    {
        if (!is_array($domain)) {
            throw CoercionException::invalidDomainValue($field, self::CAST_ARRAY, $domain);
        }

        try {
            return json_encode($domain, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw CoercionException::invalidDomainValue(
                $field,
                self::CAST_ARRAY,
                $domain,
                $e->getMessage(),
            );
        }
    }
}
