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
use Waaseyaa\Entity\Tests\Fixtures\AttributeFirstEntities\ConstraintsRequiredTitleFixture;
use Waaseyaa\Entity\Tests\Unit\Validation\Fixture\FieldableEntityDouble;
use Waaseyaa\Entity\Validation\EntityTypeValidationConstraints;
use Waaseyaa\Entity\Validation\EntityValidator;

require_once __DIR__ . '/../../Fixtures/AttributeFirstEntities/ValidationConstraintsFixtures.php';

#[CoversClass(EntityTypeValidationConstraints::class)]
final class EntityTypeValidationConstraintsTest extends TestCase
{
    protected function setUp(): void
    {
        EntityType::clearFromClassCache();
    }

    #[Test]
    public function manualConstraintsReplaceDerivedForSameField(): void
    {
        // Pattern 1: ConstraintsRequiredTitleFixture has `#[Field(required: true)]`
        // on `title`; manual constraints come through fromClass() overrides.
        $type = EntityType::fromClass(
            class: ConstraintsRequiredTitleFixture::class,
            constraints: ['title' => [new Length(max: 3)]],
        );

        $merged = EntityTypeValidationConstraints::forEntityType($type);

        $entity = $this->stubEntity(['title' => '']);
        $violations = (new EntityValidator(Validation::createValidator()))->validate($entity, $merged);

        self::assertCount(0, $violations, 'Manual constraints replaced derived NotBlank; empty title is allowed by Length(max:3).');
    }

    #[Test]
    public function manualConstraintsAugmentFieldsNotInManual(): void
    {
        $type = EntityType::fromClass(
            class: ConstraintsRequiredTitleFixture::class,
            constraints: ['slug' => [new NotBlank()]],
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
