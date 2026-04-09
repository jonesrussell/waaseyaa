<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Validation;

use BackedEnum;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * Builds per-field Symfony {@see Constraint} lists from entity type field definition arrays.
 *
 * Full metadata → constraint mapping: `docs/specs/entity-system.md` (Field definitions → constraints, #1182).
 */
final class FieldDefinitionConstraintBuilder
{
    /**
     * @param array<string, mixed> $fieldDefinitions
     *
     * @return array<string, list<Constraint>>
     */
    public static function build(array $fieldDefinitions): array
    {
        $out = [];
        foreach ($fieldDefinitions as $name => $def) {
            if (!is_array($def)) {
                continue;
            }
            /** @var array<string, mixed> $def */
            $constraints = self::constraintsForField($def);
            if ($constraints !== []) {
                $out[$name] = $constraints;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $def
     *
     * @return list<Constraint>
     */
    private static function constraintsForField(array $def): array
    {
        $constraints = [];
        $type = (string) ($def['type'] ?? 'string');
        $required = self::truthy($def['required'] ?? false);

        if ($required) {
            $constraints[] = self::requiredConstraintForType($type);
        }

        $length = self::lengthConstraint($def);
        if ($length !== null) {
            $constraints[] = $length;
        }

        if ($type === 'email') {
            $constraints[] = new Email();
        }

        $allowed = $def['allowed_values'] ?? $def['allowedValues'] ?? null;
        if (is_array($allowed) && $allowed !== []) {
            $constraints[] = new Choice(choices: array_values($allowed));
        }

        $enumClass = $def['enum_class'] ?? $def['enumClass'] ?? null;
        if (is_string($enumClass) && $enumClass !== '' && enum_exists($enumClass)
            && is_subclass_of($enumClass, BackedEnum::class)) {
            /** @var class-string<BackedEnum> $enumClass */
            $choices = array_map(static fn(BackedEnum $e): string|int => $e->value, $enumClass::cases());
            $constraints[] = new Choice(choices: $choices);
        }

        $typeConstraint = self::scalarTypeConstraint($type);
        if ($typeConstraint !== null) {
            $constraints[] = $typeConstraint;
        }

        return $constraints;
    }

    /**
     * @param array<string, mixed> $def
     */
    private static function lengthConstraint(array $def): ?Length
    {
        $maxRaw = $def['max_length'] ?? $def['maxLength'] ?? null;
        $minRaw = $def['min_length'] ?? $def['minLength'] ?? null;
        $max = is_numeric($maxRaw) ? (int) $maxRaw : null;
        $min = is_numeric($minRaw) ? (int) $minRaw : null;

        if ($max === null && $min === null) {
            return null;
        }

        if ($max !== null && $min !== null) {
            return new Length(min: $min, max: $max);
        }

        if ($max !== null) {
            return new Length(max: $max);
        }

        return new Length(min: $min);
    }

    private static function requiredConstraintForType(string $type): NotBlank|NotNull
    {
        return match ($type) {
            'boolean', 'bool',
            'integer', 'int',
            'float', 'double',
            'entity_reference',
            'timestamp', 'datetime', 'datetime_immutable' => new NotNull(),
            default => new NotBlank(),
        };
    }

    private static function scalarTypeConstraint(string $type): ?Type
    {
        return match ($type) {
            'boolean', 'bool' => new Type('bool'),
            'integer', 'int' => new Type('int'),
            'float', 'double' => new Type('float'),
            'string', 'email', 'text', 'slug' => new Type('string'),
            'array', 'json' => new Type('array'),
            default => null,
        };
    }

    private static function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}
