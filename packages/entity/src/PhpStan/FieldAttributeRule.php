<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\PhpStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\Attribute\FieldTypeInferrer;
use Waaseyaa\Entity\ContentEntityBase;

/**
 * Lints {@see Field} attribute usage at static-analysis time, mirroring the
 * checks {@see FieldTypeInferrer::infer()} performs at runtime so misuses are
 * surfaced in CI before the kernel ever boots.
 *
 * Detection rules (each error has a stable identifier):
 *   - `field.notEntity`        — declaring class does not extend ContentEntityBase
 *                                (cascade gate: skips the other checks for that property)
 *   - `field.nonPublic`        — property is not public
 *   - `field.cannotInfer`      — no `type:` arg and PHP type is missing/union/intersection
 *   - `field.unknownType`      — explicit `type:` is not in FieldTypeInferrer::VALID_TYPE_IDS
 *   - `field.incompatibleType` — explicit `type:` conflicts with the property's PHP type
 *
 * @implements Rule<Node\Stmt\Property>
 */
final class FieldAttributeRule implements Rule
{
    private const FIELD_ATTRIBUTE_FQCN = Field::class;
    private const ENTITY_BASE_FQCN = ContentEntityBase::class;

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    public function getNodeType(): string
    {
        return Node\Stmt\Property::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $fieldAttribute = $this->findFieldAttribute($node);
        if ($fieldAttribute === null) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            // Anonymous class or out-of-class context — runtime would never
            // call infer() for these. Skip silently.
            return [];
        }

        $className = $classReflection->getName();
        $errors = [];

        // Each PropertyItem under one Property statement is a separately-named
        // property; check each. (Most cases have one.)
        foreach ($node->props as $propertyItem) {
            $propertyName = $propertyItem->name->toString();

            // FR-006 cascade gate: if class is not a ContentEntityBase, report
            // that and skip the rest for this property.
            if (!$this->extendsContentEntityBase($classReflection)) {
                $errors[] = RuleErrorBuilder::message(\sprintf(
                    '#[Field] used on %s::$%s but %s does not extend %s',
                    $className,
                    $propertyName,
                    $className,
                    self::ENTITY_BASE_FQCN,
                ))
                    ->identifier('field.notEntity')
                    ->line($node->getStartLine())
                    ->build();

                continue;
            }

            // FR-001: must be public.
            if (!$node->isPublic()) {
                $errors[] = RuleErrorBuilder::message(\sprintf(
                    'Field attribute requires public property; got %s on %s::$%s',
                    $this->describeVisibility($node),
                    $className,
                    $propertyName,
                ))
                    ->identifier('field.nonPublic')
                    ->line($node->getStartLine())
                    ->build();
            }

            $explicitType = $this->extractExplicitTypeId($fieldAttribute);
            $phpTypeName = $this->resolveSingleNamedType($node->type);
            $location = \sprintf('%s::$%s', $className, $propertyName);

            if ($explicitType === null) {
                // FR-002 / FR-003: must be inferable from PHP type.
                $reason = $this->describeUninferableType($node->type);
                if ($reason !== null) {
                    $errors[] = RuleErrorBuilder::message(\sprintf(
                        'Cannot infer field type for %s (%s). Hint: declare a supported property type (string, int, bool, float, array, \DateTimeImmutable, or a backed enum) or pass type: explicitly to #[Field].',
                        $location,
                        $reason,
                    ))
                        ->identifier('field.cannotInfer')
                        ->line($node->getStartLine())
                        ->build();
                }
                continue;
            }

            // FR-004: explicit type must be a known field-type id.
            if (!\in_array($explicitType, FieldTypeInferrer::VALID_TYPE_IDS, true)) {
                $errors[] = RuleErrorBuilder::message(\sprintf(
                    'Unknown field type id "%s" on %s. Valid ids: %s. Hint: pass one of the registered field-type ids to #[Field(type: ...)] or omit it to use inference.',
                    $explicitType,
                    $location,
                    \implode(', ', FieldTypeInferrer::VALID_TYPE_IDS),
                ))
                    ->identifier('field.unknownType')
                    ->line($node->getStartLine())
                    ->build();
                continue;
            }

            // FR-005: explicit type must be compatible with the inferred type.
            $settings = [];
            $inferred = FieldTypeInferrer::inferFromPhpTypeName($phpTypeName, $settings);
            if ($inferred === null || $inferred === $explicitType) {
                continue;
            }

            if (!$this->areCompatible($inferred, $explicitType)) {
                $errors[] = RuleErrorBuilder::message(\sprintf(
                    'Conflicting field type for %s: PHP type "%s" infers field type "%s" but #[Field(type: "%s")] was given. Hint: remove the explicit type:, change the property type, or pick a compatible field-type id.',
                    $location,
                    $phpTypeName,
                    $inferred,
                    $explicitType,
                ))
                    ->identifier('field.incompatibleType')
                    ->line($node->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    private function findFieldAttribute(Node\Stmt\Property $node): ?Node\Attribute
    {
        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ($attr->name->toString() === self::FIELD_ATTRIBUTE_FQCN) {
                    return $attr;
                }
            }
        }

        return null;
    }

    private function extendsContentEntityBase(\PHPStan\Reflection\ClassReflection $classReflection): bool
    {
        if (!$this->reflectionProvider->hasClass(self::ENTITY_BASE_FQCN)) {
            // ContentEntityBase missing from autoloader — fail open rather than
            // mass-flagging every entity.
            return true;
        }

        $entityBase = $this->reflectionProvider->getClass(self::ENTITY_BASE_FQCN);

        return $classReflection->getName() === $entityBase->getName()
            || $classReflection->isSubclassOfClass($entityBase);
    }

    private function describeVisibility(Node\Stmt\Property $node): string
    {
        if ($node->isPrivate()) {
            return 'private';
        }
        if ($node->isProtected()) {
            return 'protected';
        }
        return 'unspecified';
    }

    private function extractExplicitTypeId(Node\Attribute $attribute): ?string
    {
        // Look for a named `type:` arg first; fall back to first positional.
        foreach ($attribute->args as $i => $arg) {
            $matchesPositional = ($arg->name === null && $i === 0);
            $matchesNamed = ($arg->name !== null && $arg->name->toString() === 'type');
            if (!$matchesPositional && !$matchesNamed) {
                continue;
            }

            if ($arg->value instanceof Node\Scalar\String_) {
                return $arg->value->value;
            }

            // Non-literal expression — out of scope for static analysis.
            return null;
        }

        return null;
    }

    private function resolveSingleNamedType(?Node $type): ?string
    {
        if ($type === null) {
            return null;
        }

        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }

        if ($type instanceof Node\Identifier) {
            // Built-in scalar like "string", "int".
            return $type->toString();
        }

        if ($type instanceof Node\Name) {
            return $type->toString();
        }

        // Union / intersection / unknown — caller treats as not single-named.
        return null;
    }

    private function describeUninferableType(?Node $type): ?string
    {
        if ($type === null) {
            return 'property has no type declaration';
        }

        if ($type instanceof Node\UnionType) {
            return 'union types are not supported';
        }

        if ($type instanceof Node\IntersectionType) {
            return 'intersection types are not supported';
        }

        $resolved = $this->resolveSingleNamedType($type);
        if ($resolved === null) {
            // Earlier branches caught union/intersection/null; PHP property
            // types reach here only as Identifier/Name/NullableType. No real
            // syntax produces a fall-through, so silently allow the rule
            // to skip rather than emit a misleading error.
            return null;
        }

        $settings = [];
        if (FieldTypeInferrer::inferFromPhpTypeName($resolved, $settings) === null) {
            return \sprintf('PHP type "%s" is not in the inference table', $resolved);
        }

        return null;
    }

    private function areCompatible(string $inferred, string $explicit): bool
    {
        foreach (FieldTypeInferrer::compatibilityGroups() as $group) {
            if (\in_array($inferred, $group, true) && \in_array($explicit, $group, true)) {
                return true;
            }
        }

        return false;
    }
}
