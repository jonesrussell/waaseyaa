<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\Tests\Unit\TestEntity;
use Waaseyaa\Entity\Tests\Unit\Validation\Fixture\FieldableEntityDouble;
use Waaseyaa\Entity\Validation\EntityTypeValidationConstraints;
use Waaseyaa\Entity\Validation\EntityValidator;

#[CoversClass(EntityTypeValidationConstraints::class)]
final class EntityTypeValidationConstraintsTest extends TestCase
{
    #[Test]
    public function manualConstraintsReplaceDerivedForSameField(): void
    {
        $type = new EntityType(
            id: 'x',
            label: 'X',
            class: TestEntity::class,
            fieldDefinitions: [
                'title' => ['type' => 'string', 'required' => true],
            ],
            constraints: [
                'title' => [new Length(max: 3)],
            ],
        );

        $merged = EntityTypeValidationConstraints::forEntityType($type);

        $entity = $this->stubEntity(['title' => '']);
        $violations = (new EntityValidator(Validation::createValidator()))->validate($entity, $merged);

        self::assertCount(0, $violations, 'Manual constraints replaced derived NotBlank; empty title is allowed by Length(max:3).');
    }

    #[Test]
    public function manualConstraintsAugmentFieldsNotInManual(): void
    {
        $type = new EntityType(
            id: 'x',
            label: 'X',
            class: TestEntity::class,
            fieldDefinitions: [
                'title' => ['type' => 'string', 'required' => true],
            ],
            constraints: [
                'slug' => [new NotBlank()],
            ],
        );

        $merged = EntityTypeValidationConstraints::forEntityType($type);
        $entity = $this->stubEntity(['title' => 'ok', 'slug' => '']);

        $violations = (new EntityValidator(Validation::createValidator()))->validate($entity, $merged);

        self::assertGreaterThan(0, $violations->count());
        self::assertSame('slug', $violations->get(0)->getPropertyPath());
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
