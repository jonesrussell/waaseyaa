<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Waaseyaa\Entity\Tests\Unit\Cast\Fixture\SampleIntEnum;
use Waaseyaa\Entity\Tests\Unit\Cast\Fixture\SampleStringEnum;
use Waaseyaa\Entity\Tests\Unit\Validation\Fixture\FieldableEntityDouble;
use Waaseyaa\Entity\Validation\EntityValidator;
use Waaseyaa\Entity\Validation\FieldDefinitionConstraintBuilder;
use Waaseyaa\Field\Item\EnumFieldTypeException;

#[CoversClass(FieldDefinitionConstraintBuilder::class)]
final class FieldDefinitionConstraintBuilderTest extends TestCase
{
    #[Test]
    public function requiredStringRejectsBlank(): void
    {
        $entity = $this->stubEntity(['title' => '']);
        $constraints = FieldDefinitionConstraintBuilder::build([
            'title' => ['type' => 'string', 'required' => true],
        ]);

        $violations = (new EntityValidator(Validation::createValidator()))->validate($entity, $constraints);

        self::assertGreaterThan(0, $violations->count());
        self::assertSame('title', $violations->get(0)->getPropertyPath());
    }

    #[Test]
    public function optionalFieldAllowsNull(): void
    {
        $entity = $this->stubEntity(['subtitle' => null]);
        $constraints = FieldDefinitionConstraintBuilder::build([
            'subtitle' => ['type' => 'string', 'required' => false],
        ]);

        $violations = (new EntityValidator(Validation::createValidator()))->validate($entity, $constraints);

        self::assertCount(0, $violations);
    }

    #[Test]
    public function allowedValuesRejectsInvalidChoice(): void
    {
        $entity = $this->stubEntity(['status' => 'archived']);
        $constraints = FieldDefinitionConstraintBuilder::build([
            'status' => [
                'type' => 'string',
                'allowed_values' => ['draft', 'published'],
            ],
        ]);

        $violations = (new EntityValidator(Validation::createValidator()))->validate($entity, $constraints);

        self::assertGreaterThan(0, $violations->count());
        self::assertSame('status', $violations->get(0)->getPropertyPath());
    }

    #[Test]
    public function camelCaseAllowedValuesAliasWorks(): void
    {
        $entity = $this->stubEntity(['status' => 'ok']);
        $constraints = FieldDefinitionConstraintBuilder::build([
            'status' => [
                'type' => 'string',
                'allowedValues' => ['ok'],
            ],
        ]);

        $violations = (new EntityValidator(Validation::createValidator()))->validate($entity, $constraints);

        self::assertCount(0, $violations);
    }

    #[Test]
    public function maxLengthRejectsLongString(): void
    {
        $entity = $this->stubEntity(['code' => 'abcdef']);
        $constraints = FieldDefinitionConstraintBuilder::build([
            'code' => ['type' => 'string', 'maxLength' => 3],
        ]);

        $violations = (new EntityValidator(Validation::createValidator()))->validate($entity, $constraints);

        self::assertGreaterThan(0, $violations->count());
        self::assertSame('code', $violations->get(0)->getPropertyPath());
    }

    #[Test]
    public function emailTypeRejectsInvalidEmail(): void
    {
        $entity = $this->stubEntity(['mail' => 'not-an-email']);
        $constraints = FieldDefinitionConstraintBuilder::build([
            'mail' => ['type' => 'email'],
        ]);

        $violations = (new EntityValidator(Validation::createValidator()))->validate($entity, $constraints);

        self::assertGreaterThan(0, $violations->count());
        self::assertSame('mail', $violations->get(0)->getPropertyPath());
    }

    #[Test]
    public function emailTypeAcceptsValidEmail(): void
    {
        $entity = $this->stubEntity(['mail' => 'user@example.com']);
        $constraints = FieldDefinitionConstraintBuilder::build([
            'mail' => ['type' => 'email'],
        ]);

        $violations = (new EntityValidator(Validation::createValidator()))->validate($entity, $constraints);

        self::assertCount(0, $violations);
    }

    #[Test]
    public function enumTypeRejectsValueOutsideStringBackedCases(): void
    {
        $entity = $this->stubEntity(['letter' => 'z']);
        $constraints = FieldDefinitionConstraintBuilder::build([
            'letter' => ['type' => 'enum', 'enum_class' => SampleStringEnum::class],
        ]);

        $violations = (new EntityValidator(Validation::createValidator()))->validate($entity, $constraints);

        self::assertGreaterThan(0, $violations->count());
        self::assertSame('letter', $violations->get(0)->getPropertyPath());
    }

    #[Test]
    public function enumTypeAcceptsValidStringBackedCase(): void
    {
        $entity = $this->stubEntity(['letter' => 'a']);
        $constraints = FieldDefinitionConstraintBuilder::build([
            'letter' => ['type' => 'enum', 'enum_class' => SampleStringEnum::class],
        ]);

        $violations = (new EntityValidator(Validation::createValidator()))->validate($entity, $constraints);

        self::assertCount(0, $violations);
    }

    #[Test]
    public function enumTypeRejectsValueOutsideIntBackedCases(): void
    {
        $entity = $this->stubEntity(['count' => 99]);
        $constraints = FieldDefinitionConstraintBuilder::build([
            'count' => ['type' => 'enum', 'enum_class' => SampleIntEnum::class],
        ]);

        $violations = (new EntityValidator(Validation::createValidator()))->validate($entity, $constraints);

        self::assertGreaterThan(0, $violations->count());
        self::assertSame('count', $violations->get(0)->getPropertyPath());
    }

    #[Test]
    public function stringTypeWithEnumClassDoesNotAddChoiceConstraint(): void
    {
        // Legacy 'string + enum_class' bridge is gone (C-004): a value outside
        // the enum cases must NOT produce a violation when type is 'string'.
        $entity = $this->stubEntity(['letter' => 'z']);
        $constraints = FieldDefinitionConstraintBuilder::build([
            'letter' => ['type' => 'string', 'enum_class' => SampleStringEnum::class],
        ]);

        $violations = (new EntityValidator(Validation::createValidator()))->validate($entity, $constraints);

        self::assertCount(0, $violations);
    }

    #[Test]
    public function enumTypeWithoutEnumClassPropagatesMissingEnumClass(): void
    {
        try {
            FieldDefinitionConstraintBuilder::build([
                'letter' => ['type' => 'enum'],
            ]);
            self::fail('Expected EnumFieldTypeException::MISSING_ENUM_CLASS to propagate.');
        } catch (EnumFieldTypeException $e) {
            self::assertSame(EnumFieldTypeException::MISSING_ENUM_CLASS, $e->variant);
        }
    }

    #[Test]
    public function requiredBooleanAllowsFalse(): void
    {
        $entity = $this->stubEntity(['active' => false]);
        $constraints = FieldDefinitionConstraintBuilder::build([
            'active' => ['type' => 'boolean', 'required' => true],
        ]);

        $violations = (new EntityValidator(Validation::createValidator()))->validate($entity, $constraints);

        self::assertCount(0, $violations);
    }

    #[Test]
    public function requiredBooleanRejectsNull(): void
    {
        $entity = $this->stubEntity(['active' => null]);
        $constraints = FieldDefinitionConstraintBuilder::build([
            'active' => ['type' => 'boolean', 'required' => true],
        ]);

        $violations = (new EntityValidator(Validation::createValidator()))->validate($entity, $constraints);

        self::assertGreaterThan(0, $violations->count());
        self::assertSame('active', $violations->get(0)->getPropertyPath());
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stubEntity(array $values): FieldableEntityDouble
    {
        $entity = $this->createMock(FieldableEntityDouble::class);
        $entity->method('get')->willReturnCallback(
            static fn (string $name): mixed => $values[$name] ?? null,
        );

        return $entity;
    }
}
