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
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Field\FieldDefinitionInterface;
use Waaseyaa\Field\FieldStorage;

/**
 * Builds per-field Symfony {@see Constraint} lists from entity field definitions.
 *
 * Full metadata → constraint mapping: `docs/specs/entity-system.md` (Field definitions → constraints, #1182).
 */
final class FieldDefinitionConstraintBuilder
{
    /**
     * @param array<string, FieldDefinitionInterface|array<string, mixed>> $fieldDefinitions
     *
     * @return array<string, list<Constraint>>
     */
    public static function build(array $fieldDefinitions): array
    {
        $out = [];
        foreach ($fieldDefinitions as $name => $def) {
            $constraints = self::constraintsForField($name, $def);
            if ($constraints !== []) {
                $out[$name] = $constraints;
            }
        }

        return $out;
    }

    /**
     * @return list<Constraint>
     */
    private static function constraintsForField(string $fieldName, FieldDefinitionInterface|array $def): array
    {
        $def = self::normalizeDefinition($fieldName, $def);
        $constraints = [];
        $type = $def->getType();
        $required = $def->isRequired();

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

        $allowed = $def->getSetting('allowed_values') ?? $def->getSetting('allowedValues');
        if (is_array($allowed) && $allowed !== []) {
            $constraints[] = new Choice(choices: array_values($allowed));
        }

        $enumClass = $def->getSetting('enum_class') ?? $def->getSetting('enumClass');
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
     * @param FieldDefinitionInterface|array<string, mixed> $definition
     */
    private static function normalizeDefinition(string $fieldName, FieldDefinitionInterface|array $definition): FieldDefinitionInterface
    {
        if ($definition instanceof FieldDefinitionInterface) {
            return $definition;
        }
        $settings = $definition['settings'] ?? [];
        if (!is_array($settings)) {
            $settings = [];
        }
        foreach ($definition as $key => $value) {
            if (!in_array($key, ['type', 'label', 'description', 'required', 'readOnly', 'read_only', 'cardinality', 'translatable', 'revisionable', 'default', 'defaultValue', 'settings', 'constraints', 'stored'], true)) {
                $settings[$key] = $value;
            }
        }
        $stored = $definition['stored'] ?? FieldStorage::Column;
        if (is_string($stored)) {
            $stored = FieldStorage::tryFrom($stored) ?? FieldStorage::Column;
        }
        if (!$stored instanceof FieldStorage) {
            $stored = FieldStorage::Column;
        }

        return new FieldDefinition(
            name: $fieldName,
            type: (string) ($definition['type'] ?? 'string'),
            cardinality: (int) ($definition['cardinality'] ?? 1),
            settings: $settings,
            targetEntityTypeId: '',
            targetBundle: null,
            translatable: (bool) ($definition['translatable'] ?? false),
            revisionable: (bool) ($definition['revisionable'] ?? false),
            defaultValue: $definition['defaultValue'] ?? ($definition['default'] ?? null),
            label: (string) ($definition['label'] ?? ''),
            description: (string) ($definition['description'] ?? ''),
            required: (bool) ($definition['required'] ?? false),
            readOnly: (bool) ($definition['readOnly'] ?? $definition['read_only'] ?? false),
            constraints: is_array($definition['constraints'] ?? null) ? $definition['constraints'] : [],
            stored: $stored,
        );
    }

    /**
     */
    private static function lengthConstraint(FieldDefinitionInterface $def): ?Length
    {
        $maxRaw = $def->getSetting('max_length') ?? $def->getSetting('maxLength');
        $minRaw = $def->getSetting('min_length') ?? $def->getSetting('minLength');
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
}
