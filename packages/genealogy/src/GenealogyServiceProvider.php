<?php

declare(strict_types=1);

namespace Waaseyaa\Genealogy;

use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Kernel\HttpKernel;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Genealogy\Access\GenealogyRelationshipAccessPolicy;
use Waaseyaa\Genealogy\Entity\GenealogyEvent;
use Waaseyaa\Genealogy\Entity\GenealogyFamily;
use Waaseyaa\Genealogy\Entity\GenealogyPerson;
use Waaseyaa\Genealogy\Entity\GenealogyTree;
use Waaseyaa\Genealogy\Service\GenealogyFamilyService;
use Waaseyaa\Genealogy\Service\GenealogyPedigreeService;
use Waaseyaa\Genealogy\Ssr\GenealogySsrController;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class GenealogyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType($this->treeEntityType());
        $this->entityType($this->personEntityType());
        $this->entityType($this->familyEntityType());
        $this->entityType($this->eventEntityType());

        $this->singleton(GenealogyPedigreeService::class, function (): GenealogyPedigreeService {
            /** @var EntityTypeManager $manager */
            $manager = $this->resolve(EntityTypeManager::class);

            return new GenealogyPedigreeService($manager);
        });

        $this->singleton(GenealogyFamilyService::class, function (): GenealogyFamilyService {
            /** @var EntityTypeManager $manager */
            $manager = $this->resolve(EntityTypeManager::class);

            return new GenealogyFamilyService($manager);
        });
    }

    public function configureHttpKernel(HttpKernel $kernel): void
    {
        GenealogyBootstrap::bind($kernel->getEntityTypeManager(), $kernel->getAccessHandler());
        $kernel->getAccessHandler()->addPolicy(
            new GenealogyRelationshipAccessPolicy(
                $kernel->getEntityTypeManager(),
                $kernel->getAccessHandler(),
            ),
        );
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'genealogy.landing',
            RouteBuilder::create('/genealogy')
                ->controller(GenealogySsrController::class . '::landing')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'genealogy.person',
            RouteBuilder::create('/genealogy/person/{id}')
                ->controller(GenealogySsrController::class . '::person')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'genealogy.family',
            RouteBuilder::create('/genealogy/family/{id}')
                ->controller(GenealogySsrController::class . '::family')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'genealogy.person.ancestors',
            RouteBuilder::create('/genealogy/person/{id}/ancestors')
                ->controller(GenealogySsrController::class . '::ancestorChart')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('id', '\\d+')
                ->build(),
        );
    }

    private function treeEntityType(): EntityType
    {
        return new EntityType(
            id: 'genealogy_tree',
            label: 'Genealogy tree',
            description: 'Tenancy root for genealogy workspace (owner, grants, community overlays).',
            class: GenealogyTree::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'display_name'],
            group: 'content',
            fieldDefinitions: GenealogyFieldDefinitions::treeFields(),
        );
    }

    private function personEntityType(): EntityType
    {
        return new EntityType(
            id: 'genealogy_person',
            label: 'Genealogy person',
            description: 'An individual in a genealogy dataset',
            class: GenealogyPerson::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'display_name'],
            group: 'content',
            fieldDefinitions: GenealogyFieldDefinitions::personFields(),
        );
    }

    private function familyEntityType(): EntityType
    {
        return new EntityType(
            id: 'genealogy_family',
            label: 'Genealogy family',
            description: 'A family or household group',
            class: GenealogyFamily::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'display_name'],
            group: 'content',
            fieldDefinitions: GenealogyFieldDefinitions::familyFields(),
        );
    }

    private function eventEntityType(): EntityType
    {
        return new EntityType(
            id: 'genealogy_event',
            label: 'Genealogy event',
            description: 'A vital or narrative event',
            class: GenealogyEvent::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'display_name'],
            group: 'content',
            fieldDefinitions: GenealogyFieldDefinitions::eventFields(),
        );
    }
}
