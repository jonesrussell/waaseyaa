<?php

declare(strict_types=1);

namespace Waaseyaa\Genealogy\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\EntityStorage\SqlEntityStorage;
use Waaseyaa\EntityStorage\SqlSchemaHandler;
use Waaseyaa\Field\FieldDefinitionRegistry;
use Waaseyaa\Genealogy\Entity\GenealogyFamily;
use Waaseyaa\Genealogy\Entity\GenealogyPerson;
use Waaseyaa\Genealogy\GenealogyRelationshipType;
use Waaseyaa\Genealogy\Service\GenealogyFamilyService;
use Waaseyaa\Relationship\Relationship;

#[CoversClass(GenealogyFamilyService::class)]
final class GenealogyFamilyServiceTest extends TestCase
{
    private function makeManager(): EntityTypeManager
    {
        $database = DBALDatabase::createSqlite();
        $database->getConnection()->executeStatement('PRAGMA foreign_keys = ON');
        $dispatcher = new EventDispatcher();
        $registry = new FieldDefinitionRegistry();

        $manager = new EntityTypeManager(
            $dispatcher,
            function (EntityTypeInterface $definition) use ($database, $dispatcher, $registry): SqlEntityStorage {
                (new SqlSchemaHandler($definition, $database, $registry))->ensureTable();

                return new SqlEntityStorage($definition, $database, $dispatcher, $registry);
            },
            null,
            $registry,
        );

        ContentEntityBase::setFieldRegistry($registry);

        $manager->registerEntityType(new EntityType(
            id: 'relationship',
            label: 'Relationship',
            class: Relationship::class,
            keys: [
                'id' => 'rid',
                'uuid' => 'uuid',
                'label' => 'relationship_type',
                'bundle' => 'relationship_type',
            ],
            group: 'content',
            fieldDefinitions: [
                'relationship_type' => ['type' => 'string', 'required' => true, 'weight' => 0],
                'from_entity_type' => ['type' => 'string', 'required' => true, 'weight' => 1],
                'from_entity_id' => ['type' => 'string', 'required' => true, 'weight' => 2],
                'to_entity_type' => ['type' => 'string', 'required' => true, 'weight' => 3],
                'to_entity_id' => ['type' => 'string', 'required' => true, 'weight' => 4],
                'directionality' => ['type' => 'string', 'weight' => 5, 'default' => 'directed'],
                'status' => ['type' => 'boolean', 'weight' => 6, 'default' => 1],
            ],
        ));

        $manager->registerEntityType(new EntityType(
            id: 'genealogy_person',
            label: 'Genealogy person',
            class: GenealogyPerson::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'display_name'],
            group: 'content',
            fieldDefinitions: [
                'display_name' => ['type' => 'string', 'required' => true, 'weight' => 0],
                'status' => ['type' => 'boolean', 'weight' => 10, 'default' => 1],
            ],
        ));

        $manager->registerEntityType(new EntityType(
            id: 'genealogy_family',
            label: 'Genealogy family',
            class: GenealogyFamily::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'display_name'],
            group: 'content',
            fieldDefinitions: [
                'display_name' => ['type' => 'string', 'required' => true, 'weight' => 0],
                'status' => ['type' => 'boolean', 'weight' => 10, 'default' => 1],
            ],
        ));

        return $manager;
    }

    protected function tearDown(): void
    {
        ContentEntityBase::setFieldRegistry(null);
    }

    #[Test]
    public function member_person_ids_reads_member_of_family_edges(): void
    {
        $manager = $this->makeManager();
        $personStorage = $manager->getStorage('genealogy_person');
        $familyStorage = $manager->getStorage('genealogy_family');
        $relStorage = $manager->getStorage('relationship');

        $family = $familyStorage->create(['display_name' => 'House']);
        $familyStorage->save($family);
        $m1 = $personStorage->create(['display_name' => 'M1']);
        $personStorage->save($m1);
        $m2 = $personStorage->create(['display_name' => 'M2']);
        $personStorage->save($m2);

        foreach ([$m1, $m2] as $member) {
            $e = $relStorage->create([
                'relationship_type' => GenealogyRelationshipType::MEMBER_OF_FAMILY,
                'from_entity_type' => 'genealogy_person',
                'from_entity_id' => (string) $member->id(),
                'to_entity_type' => 'genealogy_family',
                'to_entity_id' => (string) $family->id(),
                'directionality' => 'directed',
                'status' => 1,
            ]);
            $relStorage->save($e);
        }

        $service = new GenealogyFamilyService($manager);

        self::assertSame([(string) $m1->id(), (string) $m2->id()], $service->memberPersonIds((string) $family->id()));
    }
}
