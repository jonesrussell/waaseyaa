<?php

declare(strict_types=1);

namespace Waaseyaa\Groups;

use Waaseyaa\Entity\EntityType;
use Waaseyaa\Field\FieldStorage;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

/**
 * Registers the `group` content entity type and its `group_type` bundle
 * config entity.
 *
 * Ships with zero pre-registered bundles. Products declare their own
 * GroupType config entities and register bundle-scoped fields via
 * EntityTypeManager::addBundleFields().
 */
final class GroupsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'group',
            label: 'Group',
            description: 'Multi-bundle group of members, actors, or subjects.',
            class: Group::class,
            keys: [
                'id' => 'gid',
                'uuid' => 'uuid',
                'bundle' => 'type',
                'label' => 'name',
                'langcode' => 'langcode',
            ],
            bundleEntityType: 'group_type',
            group: 'groups',
            fieldDefinitions: [
                'status' => [
                    'type' => 'integer',
                    'default' => 1,
                    'stored' => FieldStorage::Data,
                    'label' => 'Status',
                    'description' => 'Whether the group is published.',
                ],
                'created_at' => [
                    'type' => 'integer',
                    'stored' => FieldStorage::Data,
                    'label' => 'Created at',
                ],
                'updated_at' => [
                    'type' => 'integer',
                    'stored' => FieldStorage::Data,
                    'label' => 'Updated at',
                ],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'group_type',
            label: 'Group type',
            description: 'Declares a Group bundle.',
            class: GroupType::class,
            keys: [
                'id' => 'id',
                'label' => 'label',
            ],
            group: 'groups',
            fieldDefinitions: [
                'description' => [
                    'type' => 'text',
                    'label' => 'Description',
                    'description' => 'Human-readable description of this group type.',
                    'weight' => 5,
                ],
            ],
        ));
    }
}
