<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Cast\Exception;

/**
 * Thrown when a value cannot be converted for an entity field cast.
 */
final class CastException extends \InvalidArgumentException
{
    public static function invalidStoredValue(
        string $field,
        string $cast,
        mixed $value,
        ?string $detail = null,
        ?\Throwable $previous = null,
    ): self {
        $message = sprintf('Cannot cast stored value for field "%s" to %s.', $field, $cast);
        if ($detail !== null && $detail !== '') {
            $message .= ' ' . $detail;
        }

        return new self($message, 0, $previous);
    }

    public static function invalidDomainValue(
        string $field,
        string $cast,
        mixed $value,
        ?string $detail = null,
    ): self {
        $message = sprintf('Cannot cast domain value for field "%s" to storage for %s.', $field, $cast);
        if ($detail !== null && $detail !== '') {
            $message .= ' ' . $detail;
        }

        return new self($message);
    }

    /**
     * Non-enum class-strings are reserved for value-object casts (#1184).
     */
    public static function unsupportedValueObjectCast(string $field, string $class): self
    {
        return new self(sprintf(
            'Cast to class "%s" for field "%s" is not supported yet (value-object casts: #1184).',
            $class,
            $field,
        ));
    }

    public static function invalidCastSpec(string $field): self
    {
        return new self(sprintf('Invalid cast specification for field "%s".', $field));
    }

    public static function unknownBuiltinCast(string $field, string $token): self
    {
        return new self(sprintf('Unknown built-in cast "%s" for field "%s".', $token, $field));
    }

    public static function invalidDatetimeStorage(string $field, string $storage): self
    {
        return new self(sprintf(
            'Invalid datetime storage format "%s" for field "%s" (expected "unix" or "iso8601").',
            $storage,
            $field,
        ));
    }

    public static function carbonImmutableNotInstalled(string $field): self
    {
        return new self(sprintf(
            'Field "%s" uses cast domain "carbon_immutable" but nesbot/carbon is not installed.',
            $field,
        ));
    }
}
