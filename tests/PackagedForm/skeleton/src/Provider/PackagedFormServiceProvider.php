<?php

declare(strict_types=1);

namespace App\Provider;

use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class PackagedFormServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $entityTypeManager = $this->resolve(EntityTypeManager::class);
        assert($entityTypeManager instanceof EntityTypeManager);

        $entityTypeManager->addBundleFields('group', 'packaged_fixture', [
            'fixture_code' => new FieldDefinition(
                name: 'fixture_code',
                type: 'string',
                targetEntityTypeId: 'group',
                targetBundle: 'packaged_fixture',
            ),
        ]);
    }
}
