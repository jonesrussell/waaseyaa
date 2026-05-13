<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\Exception\InvalidEntityTypeException;
use Waaseyaa\Entity\TranslatableInterface;

/**
 * Unit tests for WP02: EntityType boot validation for translatable types.
 *
 * Covers:
 * - T006: InvalidEntityTypeException static factories exist and produce correct messages.
 * - T007: EntityType::__construct validates translatable types at boot.
 * - T008: bundleEntityType does not propagate translatability (FR-005).
 * - T009: Regression — translatable:false (default) boots regardless of key shape.
 */
#[CoversClass(EntityType::class)]
#[CoversClass(InvalidEntityTypeException::class)]
final class EntityTypeBootValidationTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Positive case: translatable=true with proper keys + TranslatableInterface
    // -------------------------------------------------------------------------

    #[Test]
    public function translatable_type_with_all_requirements_boots_cleanly(): void
    {
        $entityClass = $this->makeTranslatableEntityClass();

        $type = new EntityType(
            id: 'article',
            label: 'Article',
            class: $entityClass,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'langcode' => 'langcode',
                'default_langcode' => 'default_langcode',
            ],
            translatable: true,
        );

        self::assertTrue($type->isTranslatable());
    }

    // -------------------------------------------------------------------------
    // Negative case 1: missing langcode key
    // -------------------------------------------------------------------------

    #[Test]
    public function translatable_true_without_langcode_key_throws(): void
    {
        $entityClass = $this->makeTranslatableEntityClass();

        $this->expectException(InvalidEntityTypeException::class);
        $this->expectExceptionMessage('article');
        $this->expectExceptionMessage('"langcode"');

        new EntityType(
            id: 'article',
            label: 'Article',
            class: $entityClass,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'default_langcode' => 'default_langcode',
            ],
            translatable: true,
        );
    }

    // -------------------------------------------------------------------------
    // Negative case 2: missing default_langcode key
    // -------------------------------------------------------------------------

    #[Test]
    public function translatable_true_without_default_langcode_key_throws(): void
    {
        $entityClass = $this->makeTranslatableEntityClass();

        $this->expectException(InvalidEntityTypeException::class);
        $this->expectExceptionMessage('article');
        $this->expectExceptionMessage('"default_langcode"');

        new EntityType(
            id: 'article',
            label: 'Article',
            class: $entityClass,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'langcode' => 'langcode',
            ],
            translatable: true,
        );
    }

    // -------------------------------------------------------------------------
    // Negative case 3: entity class not implementing TranslatableInterface
    // -------------------------------------------------------------------------

    #[Test]
    public function translatable_true_with_non_translatable_class_throws(): void
    {
        $this->expectException(InvalidEntityTypeException::class);
        $this->expectExceptionMessage('article');
        $this->expectExceptionMessage('TranslatableInterface');

        new EntityType(
            id: 'article',
            label: 'Article',
            class: TestEntity::class,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'langcode' => 'langcode',
                'default_langcode' => 'default_langcode',
            ],
            translatable: true,
        );
    }

    // -------------------------------------------------------------------------
    // Bundle case: FR-005 — bundleEntityType does NOT inherit translatability
    // -------------------------------------------------------------------------

    #[Test]
    public function translatable_type_with_non_translatable_bundle_entity_type_boots_cleanly(): void
    {
        $entityClass = $this->makeTranslatableEntityClass();

        // The content type (article) is translatable.
        $articleType = new EntityType(
            id: 'article',
            label: 'Article',
            class: $entityClass,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'langcode' => 'langcode',
                'default_langcode' => 'default_langcode',
                'bundle' => 'type',
            ],
            translatable: true,
            bundleEntityType: 'article_type',
        );

        // The bundle entity type (article_type) is NOT translatable — no langcode keys needed.
        $bundleType = new EntityType(
            id: 'article_type',
            label: 'Article Type',
            class: TestEntity::class,
            keys: ['id' => 'machine_name'],
            translatable: false,
        );

        self::assertTrue($articleType->isTranslatable());
        self::assertFalse($bundleType->isTranslatable());
        self::assertSame('article_type', $articleType->getBundleEntityType());
    }

    // -------------------------------------------------------------------------
    // Regression: translatable=false (default) boots regardless of key shape
    // -------------------------------------------------------------------------

    #[Test]
    public function non_translatable_type_boots_without_langcode_keys(): void
    {
        // Existing entity types have no langcode keys — must not throw.
        $type = new EntityType(
            id: 'user',
            label: 'User',
            class: TestEntity::class,
            keys: ['id' => 'uid', 'uuid' => 'uuid'],
        );

        self::assertFalse($type->isTranslatable());
    }

    #[Test]
    public function non_translatable_type_with_non_translatable_class_boots(): void
    {
        // TestEntity does not implement TranslatableInterface — fine for non-translatable types.
        $type = new EntityType(
            id: 'config_entity',
            label: 'Config Entity',
            class: TestEntity::class,
            keys: ['id' => 'id'],
            translatable: false,
        );

        self::assertFalse($type->isTranslatable());
    }

    // -------------------------------------------------------------------------
    // T006: Factory message assertions
    // -------------------------------------------------------------------------

    #[Test]
    public function missing_langcode_key_factory_produces_correct_message(): void
    {
        $ex = InvalidEntityTypeException::missingLangcodeKey('my_type');

        self::assertStringContainsString('my_type', $ex->getMessage());
        self::assertStringContainsString('"langcode"', $ex->getMessage());
    }

    #[Test]
    public function missing_default_langcode_key_factory_produces_correct_message(): void
    {
        $ex = InvalidEntityTypeException::missingDefaultLangcodeKey('my_type');

        self::assertStringContainsString('my_type', $ex->getMessage());
        self::assertStringContainsString('"default_langcode"', $ex->getMessage());
    }

    #[Test]
    public function translatable_class_not_implementing_interface_factory_produces_correct_message(): void
    {
        $ex = InvalidEntityTypeException::translatableEntityClassNotImplementingInterface(
            'my_type',
            'My\\Entity\\Class',
        );

        self::assertStringContainsString('my_type', $ex->getMessage());
        self::assertStringContainsString('My\\Entity\\Class', $ex->getMessage());
        self::assertStringContainsString('TranslatableInterface', $ex->getMessage());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the FQCN of an anonymous class that extends ContentEntityBase
     * (which implements TranslatableInterface via WP01).
     *
     * @return class-string
     */
    private function makeTranslatableEntityClass(): string
    {
        $obj = new class ([]) extends ContentEntityBase {
            public function __construct(array $values = [])
            {
                parent::__construct($values, 'test_translatable', [
                    'id' => 'id',
                    'uuid' => 'uuid',
                    'langcode' => 'langcode',
                    'default_langcode' => 'default_langcode',
                ]);
            }
        };

        return $obj::class;
    }
}
