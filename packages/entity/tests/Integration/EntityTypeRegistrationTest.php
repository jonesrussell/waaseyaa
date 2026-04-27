<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Entity\Attribute\EntityMetadataReader;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Tests\Fixtures\AttributeFirstEntities\SimpleFixture;
use Waaseyaa\Field\FieldDefinitionRegistry;

require_once __DIR__ . '/../Fixtures/AttributeFirstEntities/FactoryTestFixtures.php';

/**
 * End-to-end regression: EntityType::fromClass() must produce a value that
 * EntityTypeManager::registerEntityType() accepts when a real
 * FieldDefinitionRegistry is wired through.
 *
 * The bug under test: pre-fix, every FieldDefinition built by
 * EntityMetadataReader::resolveFields() defaulted targetEntityTypeId to '',
 * so FieldDefinitionRegistry::registerCoreFields() rejected them with
 * "Core field ... declares targetEntityTypeId '' but is being registered
 * against entity type 'simple'."
 */
#[CoversClass(EntityType::class)]
#[CoversClass(EntityMetadataReader::class)]
final class EntityTypeRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        EntityType::clearFromClassCache();
        EntityMetadataReader::clearCache();
    }

    #[Test]
    public function fromClassProducesFieldsCarryingTheEntityTypeId(): void
    {
        $type = EntityType::fromClass(SimpleFixture::class);

        self::assertSame('simple', $type->id());

        $fields = $type->getFieldDefinitions();
        self::assertNotEmpty($fields, 'SimpleFixture should declare at least one #[Field].');

        foreach ($fields as $name => $field) {
            self::assertSame(
                'simple',
                $field->getTargetEntityTypeId(),
                \sprintf('Field "%s" should carry targetEntityTypeId "simple".', $name),
            );
        }
    }

    #[Test]
    public function registerEntityTypeAcceptsFromClassResultAgainstRealRegistry(): void
    {
        $registry = new FieldDefinitionRegistry();
        $manager = new EntityTypeManager(
            new EventDispatcher(),
            null,
            null,
            $registry,
        );

        $type = EntityType::fromClass(SimpleFixture::class);
        $manager->registerEntityType($type);

        $registered = $registry->coreFieldsFor('simple');
        self::assertNotEmpty($registered);
        self::assertArrayHasKey('title', $registered);
        self::assertArrayHasKey('count', $registered);
        self::assertArrayHasKey('active', $registered);

        foreach ($registered as $name => $field) {
            self::assertSame(
                'simple',
                $field->getTargetEntityTypeId(),
                \sprintf('Registered field "%s" should report targetEntityTypeId "simple".', $name),
            );
        }
    }
}
