<?php

declare(strict_types=1);

namespace Waaseyaa\Taxonomy;

use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class TaxonomyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(EntityType::fromClass(
            Term::class,
            group: 'taxonomy',
            bundleEntityType: 'taxonomy_vocabulary',
        ));

        // Vocabulary is a configuration entity (ConfigEntityBase). Field-attribute
        // reflection does not apply; keep an explicit EntityType registration.
        $this->entityType(new EntityType(
            id: 'taxonomy_vocabulary',
            label: 'Vocabulary',
            description: 'Term groupings that define classification systems',
            class: Vocabulary::class,
            keys: ['id' => 'vid', 'label' => 'name'],
            group: 'taxonomy',
        ));
    }
}
