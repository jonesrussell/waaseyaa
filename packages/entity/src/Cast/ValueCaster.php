<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Cast;

use BackedEnum;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use ReflectionEnum;
use Waaseyaa\Entity\Cast\Exception\CastException;

/**
 * Converts values between storage shapes and PHP domain types for entity field casts.
 *
 * Does not depend on Carbon; datetime handling stays on {@see DateTimeImmutable} until #1183.
 */
final class ValueCaster
{
    private const BUILTIN_INT = 'int';

    private const BUILTIN_FLOAT = 'float';

    private const BUILTIN_BOOL = 'bool';

    private const BUILTIN_STRING = 'string';

    private const BUILTIN_ARRAY = 'array';

    private const BUILTIN_DATETIME_IMMUTABLE = 'datetime_immutable';

    /**
     * @var list<string>
     */
    private const BUILTIN_TOKENS = [
        self::BUILTIN_INT,
        self::BUILTIN_FLOAT,
        self::BUILTIN_BOOL,
        self::BUILTIN_STRING,
        self::BUILTIN_ARRAY,
        self::BUILTIN_DATETIME_IMMUTABLE,
    ];

    /**
     * Storage → domain (e.g. JSON string → array, ISO-8601 → DateTimeImmutable).
     *
     * @param string|array<string, mixed> $castSpec
     */
    public function castIn(string $field, mixed $stored, string|array $castSpec): mixed
    {
        if ($stored === null) {
            return null;
        }

        $resolved = $this->resolveCastTarget($field, $castSpec);

        if ($resolved['kind'] === 'builtin') {
            return $this->castInBuiltin($field, $stored, $resolved['token']);
        }

        /** @var class-string<BackedEnum> $enumClass */
        $enumClass = $resolved['class'];

        return $this->castInBackedEnum($field, $stored, $enumClass);
    }

    /**
     * Domain → storage (canonical scalars / JSON strings as documented per kind).
     *
     * @param string|array<string, mixed> $castSpec
     */
    public function castOut(string $field, mixed $domain, string|array $castSpec): mixed
    {
        if ($domain === null) {
            return null;
        }

        $resolved = $this->resolveCastTarget($field, $castSpec);

        if ($resolved['kind'] === 'builtin') {
            return $this->castOutBuiltin($field, $domain, $resolved['token']);
        }

        /** @var class-string<BackedEnum> $enumClass */
        $enumClass = $resolved['class'];

        return $this->castOutBackedEnum($field, $domain, $enumClass);
    }

    /**
     * @return array{kind: 'builtin', token: string}|array{kind: 'enum', class: class-string<BackedEnum>}
     */
    private function resolveCastTarget(string $field, string|array $castSpec): array
    {
        $token = $this->normalizeSpecToToken($field, $castSpec);
        if ($token === '') {
            throw CastException::invalidCastSpec($field);
        }

        if (in_array($token, self::BUILTIN_TOKENS, true)) {
            return ['kind' => 'builtin', 'token' => $token];
        }

        if (!enum_exists($token)) {
            if (class_exists($token)) {
                throw CastException::unsupportedValueObjectCast($field, $token);
            }

            throw CastException::unknownBuiltinCast($field, $token);
        }

        $reflection = new ReflectionEnum($token);
        if (!$reflection->isBacked()) {
            throw CastException::invalidCastSpec($field);
        }

        /** @var class-string<BackedEnum> $token */
        return ['kind' => 'enum', 'class' => $token];
    }

    /**
     * @param string|array<string, mixed> $castSpec
     */
    private function normalizeSpecToToken(string $field, string|array $castSpec): string
    {
        if (is_string($castSpec)) {
            return $castSpec;
        }

        if (!isset($castSpec['type']) || !is_string($castSpec['type']) || $castSpec['type'] === '') {
            throw CastException::invalidCastSpec($field);
        }

        $type = $castSpec['type'];

        return $type === 'json' ? self::BUILTIN_ARRAY : $type;
    }

    private function castInBuiltin(string $field, mixed $stored, string $token): mixed
    {
        return match ($token) {
            self::BUILTIN_INT => $this->castInInt($field, $stored),
            self::BUILTIN_FLOAT => $this->castInFloat($field, $stored),
            self::BUILTIN_BOOL => $this->castInBool($field, $stored),
            self::BUILTIN_STRING => $this->castInString($field, $stored),
            self::BUILTIN_ARRAY => $this->castInArray($field, $stored),
            self::BUILTIN_DATETIME_IMMUTABLE => $this->castInDateTimeImmutable($field, $stored),
            default => throw CastException::unknownBuiltinCast($field, $token),
        };
    }

    private function castOutBuiltin(string $field, mixed $domain, string $token): mixed
    {
        return match ($token) {
            self::BUILTIN_INT => $this->castOutInt($field, $domain),
            self::BUILTIN_FLOAT => $this->castOutFloat($field, $domain),
            self::BUILTIN_BOOL => $this->castOutBool($field, $domain),
            self::BUILTIN_STRING => $this->castOutString($field, $domain),
            self::BUILTIN_ARRAY => $this->castOutArray($field, $domain),
            self::BUILTIN_DATETIME_IMMUTABLE => $this->castOutDateTimeImmutable($field, $domain),
            default => throw CastException::unknownBuiltinCast($field, $token),
        };
    }

    private function castInInt(string $field, mixed $stored): int
    {
        if (is_int($stored)) {
            return $stored;
        }

        if (is_float($stored)) {
            return (int) $stored;
        }

        if (is_string($stored)) {
            if ($stored === '') {
                throw CastException::invalidStoredValue($field, self::BUILTIN_INT, $stored, 'Empty string is not a valid integer.');
            }

            if (!is_numeric($stored)) {
                throw CastException::invalidStoredValue($field, self::BUILTIN_INT, $stored, 'Value is not numeric.');
            }

            return (int) $stored;
        }

        if (is_bool($stored)) {
            return $stored ? 1 : 0;
        }

        throw CastException::invalidStoredValue($field, self::BUILTIN_INT, $stored);
    }

    private function castOutInt(string $field, mixed $domain): int
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

        throw CastException::invalidDomainValue($field, self::BUILTIN_INT, $domain);
    }

    private function castInFloat(string $field, mixed $stored): float
    {
        if (is_float($stored)) {
            return $stored;
        }

        if (is_int($stored)) {
            return (float) $stored;
        }

        if (is_string($stored)) {
            if ($stored === '') {
                throw CastException::invalidStoredValue($field, self::BUILTIN_FLOAT, $stored, 'Empty string is not a valid float.');
            }

            if (!is_numeric($stored)) {
                throw CastException::invalidStoredValue($field, self::BUILTIN_FLOAT, $stored, 'Value is not numeric.');
            }

            return (float) $stored;
        }

        if (is_bool($stored)) {
            return $stored ? 1.0 : 0.0;
        }

        throw CastException::invalidStoredValue($field, self::BUILTIN_FLOAT, $stored);
    }

    private function castOutFloat(string $field, mixed $domain): float
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

        throw CastException::invalidDomainValue($field, self::BUILTIN_FLOAT, $domain);
    }

    private function castInBool(string $field, mixed $stored): bool
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

        throw CastException::invalidStoredValue($field, self::BUILTIN_BOOL, $stored);
    }

    private function castOutBool(string $field, mixed $domain): bool
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

        throw CastException::invalidDomainValue($field, self::BUILTIN_BOOL, $domain);
    }

    private function castInString(string $field, mixed $stored): string
    {
        if (is_string($stored)) {
            return $stored;
        }

        if (is_scalar($stored)) {
            return (string) $stored;
        }

        throw CastException::invalidStoredValue(
            $field,
            self::BUILTIN_STRING,
            $stored,
            'Only strings and scalars are supported.',
        );
    }

    private function castOutString(string $field, mixed $domain): string
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

        throw CastException::invalidDomainValue($field, self::BUILTIN_STRING, $domain);
    }

    /**
     * @return array<mixed>
     */
    private function castInArray(string $field, mixed $stored): array
    {
        if (is_array($stored)) {
            return $stored;
        }

        if (!is_string($stored)) {
            throw CastException::invalidStoredValue($field, self::BUILTIN_ARRAY, $stored);
        }

        if ($stored === '') {
            throw CastException::invalidStoredValue(
                $field,
                self::BUILTIN_ARRAY,
                $stored,
                'Empty string is not valid JSON for an array cast.',
            );
        }

        try {
            $decoded = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw CastException::invalidStoredValue(
                $field,
                self::BUILTIN_ARRAY,
                $stored,
                'Invalid JSON: ' . $e->getMessage(),
                $e,
            );
        }

        if (!is_array($decoded)) {
            throw CastException::invalidStoredValue(
                $field,
                self::BUILTIN_ARRAY,
                $stored,
                'JSON must decode to an array.',
            );
        }

        return $decoded;
    }

    /**
     * @return non-falsy-string
     */
    private function castOutArray(string $field, mixed $domain): string
    {
        if (!is_array($domain)) {
            throw CastException::invalidDomainValue($field, self::BUILTIN_ARRAY, $domain);
        }

        try {
            return json_encode($domain, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw CastException::invalidDomainValue(
                $field,
                self::BUILTIN_ARRAY,
                $domain,
                $e->getMessage(),
            );
        }
    }

    private function castInDateTimeImmutable(string $field, mixed $stored): DateTimeImmutable
    {
        if ($stored instanceof DateTimeImmutable) {
            return $stored;
        }

        if ($stored instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($stored);
        }

        if (is_int($stored)) {
            return $this->dateTimeFromUnixTimestamp($field, $stored);
        }

        if (is_string($stored)) {
            if ($stored === '') {
                throw CastException::invalidStoredValue(
                    $field,
                    self::BUILTIN_DATETIME_IMMUTABLE,
                    $stored,
                    'Empty string is not a valid datetime.',
                );
            }

            if (ctype_digit($stored)) {
                return $this->dateTimeFromUnixTimestamp($field, (int) $stored);
            }

            try {
                return new DateTimeImmutable($stored);
            } catch (DateMalformedStringException $e) {
                throw CastException::invalidStoredValue(
                    $field,
                    self::BUILTIN_DATETIME_IMMUTABLE,
                    $stored,
                    $e->getMessage(),
                    $e,
                );
            }
        }

        throw CastException::invalidStoredValue($field, self::BUILTIN_DATETIME_IMMUTABLE, $stored);
    }

    private function dateTimeFromUnixTimestamp(string $field, int $timestamp): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable('@' . (string) $timestamp);
        } catch (DateMalformedStringException $e) {
            throw CastException::invalidStoredValue(
                $field,
                self::BUILTIN_DATETIME_IMMUTABLE,
                $timestamp,
                $e->getMessage(),
                $e,
            );
        }
    }

    private function castOutDateTimeImmutable(string $field, mixed $domain): string
    {
        if ($domain instanceof DateTimeImmutable) {
            return $domain->format(DateTimeInterface::ATOM);
        }

        if ($domain instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($domain)->format(DateTimeInterface::ATOM);
        }

        if (is_string($domain) && $domain !== '') {
            return $this->castOutDateTimeImmutable($field, $this->castInDateTimeImmutable($field, $domain));
        }

        if (is_int($domain)) {
            return $this->dateTimeFromUnixTimestamp($field, $domain)->format(DateTimeInterface::ATOM);
        }

        throw CastException::invalidDomainValue($field, self::BUILTIN_DATETIME_IMMUTABLE, $domain);
    }

    /**
     * @param class-string<BackedEnum> $enumClass
     */
    private function castInBackedEnum(string $field, mixed $stored, string $enumClass): BackedEnum
    {
        if ($stored instanceof BackedEnum && $stored::class === $enumClass) {
            return $stored;
        }

        $backingType = (new ReflectionEnum($enumClass))->getBackingType();
        if ($backingType === null) {
            throw CastException::invalidCastSpec($field);
        }

        $backingName = $backingType->getName();

        if ($backingName === 'string' && is_string($stored)) {
            $case = $enumClass::tryFrom($stored);
            if ($case === null) {
                throw CastException::invalidStoredValue(
                    $field,
                    $enumClass,
                    $stored,
                    sprintf('No %s case for stored value.', $enumClass),
                );
            }

            return $case;
        }

        if ($backingName === 'int' && is_int($stored)) {
            $case = $enumClass::tryFrom($stored);
            if ($case === null) {
                throw CastException::invalidStoredValue(
                    $field,
                    $enumClass,
                    $stored,
                    sprintf('No %s case for stored value.', $enumClass),
                );
            }

            return $case;
        }

        if ($backingName === 'int' && is_string($stored) && ctype_digit($stored)) {
            return $this->castInBackedEnum($field, (int) $stored, $enumClass);
        }

        throw CastException::invalidStoredValue(
            $field,
            $enumClass,
            $stored,
            'Stored value type does not match enum backing type.',
        );
    }

    /**
     * @param class-string<BackedEnum> $enumClass
     */
    private function castOutBackedEnum(string $field, mixed $domain, string $enumClass): string|int
    {
        if ($domain instanceof BackedEnum && $domain::class === $enumClass) {
            return $domain->value;
        }

        $backingType = (new ReflectionEnum($enumClass))->getBackingType();
        if ($backingType === null) {
            throw CastException::invalidCastSpec($field);
        }

        $backingName = $backingType->getName();

        if ($backingName === 'string' && is_string($domain)) {
            $case = $enumClass::tryFrom($domain);
            if ($case === null) {
                throw CastException::invalidDomainValue(
                    $field,
                    $enumClass,
                    $domain,
                    sprintf('No %s case for value.', $enumClass),
                );
            }

            return $case->value;
        }

        if ($backingName === 'int' && is_int($domain)) {
            $case = $enumClass::tryFrom($domain);
            if ($case === null) {
                throw CastException::invalidDomainValue(
                    $field,
                    $enumClass,
                    $domain,
                    sprintf('No %s case for value.', $enumClass),
                );
            }

            return $case->value;
        }

        throw CastException::invalidDomainValue($field, $enumClass, $domain);
    }
}
