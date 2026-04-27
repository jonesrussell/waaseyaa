<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\PhpStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\Attribute\FieldTypeInferrer;
use Waaseyaa\Entity\Exception\EntityMetadataException;
use Waaseyaa\Entity\PhpStan\FieldAttributeRule;

/**
 * @extends RuleTestCase<FieldAttributeRule>
 */
final class FieldAttributeRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new FieldAttributeRule(self::createReflectionProvider());
    }

    public static function setUpBeforeClass(): void
    {
        // Fixtures live under tests/PhpStan/data/ and are not in the package's
        // PSR-4 dev autoload (path layout doesn't match). Load them so the
        // FR-007 runtime cross-check can reflect on the fixture classes.
        foreach ([
            'cannotInferUntyped',
            'cannotInferUnion',
            'unknownTypeId',
            'incompatibleType',
        ] as $fixture) {
            require_once __DIR__ . '/data/' . $fixture . '.php';
        }
    }

    public function testNonPublicProperty(): void
    {
        $expected = sprintf(
            'Field attribute requires public property; got protected on %s::$%s',
            \Waaseyaa\Entity\Tests\PhpStan\Fixtures\BadNonPublic::class,
            'secret',
        );

        $this->analyse([__DIR__ . '/data/nonPublicProperty.php'], [
            [$expected, 12],
        ]);
    }

    public function testCannotInferUntyped(): void
    {
        $expected = $this->expectedRuntimeMessage(
            \Waaseyaa\Entity\Tests\PhpStan\Fixtures\BadUntyped::class,
            'anything',
            new Field(),
        );

        $this->analyse([__DIR__ . '/data/cannotInferUntyped.php'], [
            [$expected, 12],
        ]);
    }

    public function testCannotInferUnion(): void
    {
        $expected = $this->expectedRuntimeMessage(
            \Waaseyaa\Entity\Tests\PhpStan\Fixtures\BadUnion::class,
            'either',
            new Field(),
        );

        $this->analyse([__DIR__ . '/data/cannotInferUnion.php'], [
            [$expected, 12],
        ]);
    }

    public function testUnknownTypeId(): void
    {
        $expected = $this->expectedRuntimeMessage(
            \Waaseyaa\Entity\Tests\PhpStan\Fixtures\BadUnknownType::class,
            'count',
            new Field(type: 'integerr'),
        );

        $this->analyse([__DIR__ . '/data/unknownTypeId.php'], [
            [$expected, 12],
        ]);
    }

    public function testIncompatibleType(): void
    {
        $expected = $this->expectedRuntimeMessage(
            \Waaseyaa\Entity\Tests\PhpStan\Fixtures\BadIncompatible::class,
            'count',
            new Field(type: 'integer'),
        );

        $this->analyse([__DIR__ . '/data/incompatibleType.php'], [
            [$expected, 12],
        ]);
    }

    public function testCompatibleOverrideHasNoError(): void
    {
        $this->analyse([__DIR__ . '/data/compatibleOverride.php'], []);
    }

    public function testNotEntityClass(): void
    {
        $expected = sprintf(
            '#[Field] used on %s::$%s but %s does not extend %s',
            \Waaseyaa\Entity\Tests\PhpStan\Fixtures\JustADto::class,
            'name',
            \Waaseyaa\Entity\Tests\PhpStan\Fixtures\JustADto::class,
            \Waaseyaa\Entity\ContentEntityBase::class,
        );

        $this->analyse([__DIR__ . '/data/notEntityClass.php'], [
            [$expected, 11],
        ]);
    }

    /**
     * Build the expected error message by invoking the runtime authority.
     * Enforces FR-007 (PHPStan errors string-equal runtime errors) mechanically.
     */
    private function expectedRuntimeMessage(string $fqcn, string $propertyName, Field $attribute): string
    {
        $property = new \ReflectionProperty($fqcn, $propertyName);

        try {
            FieldTypeInferrer::infer($property, $attribute);
            self::fail("Expected EntityMetadataException for {$fqcn}::\${$propertyName}");
        } catch (EntityMetadataException $e) {
            return $e->getMessage();
        }
    }
}
