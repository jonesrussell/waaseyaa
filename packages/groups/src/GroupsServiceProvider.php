<?php

declare(strict_types=1);

namespace Waaseyaa\Groups;

use Waaseyaa\Entity\EntityType;
use Waaseyaa\Field\FieldDefinition;
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
        // The `group` content entity declares its core fields with
        // `stored: FieldStorage::Data` so registry-aware queries can resolve
        // `status`/`created_at`/`updated_at` via json_extract on the bundle-
        // partitioned data table. The current `#[Field]` attribute does not
        // expose `stored:`, so we register the content type explicitly here
        // (using the @internal `_fieldDefinitions` slot) instead of via
        // EntityType::fromClass(). The Group class still carries
        // #[ContentEntityType] / #[ContentEntityKeys] for type-id discovery,
        // and bundle-scoped fields remain consumer-defined via
        // EntityTypeManager::addBundleFields().
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
            _fieldDefinitions: [
                'status' => new FieldDefinition(
                    name: 'status',
                    type: 'integer',
                    defaultValue: 1,
                    label: 'Status',
                    description: 'Whether the group is published.',
                    stored: FieldStorage::Data,
                ),
                'created_at' => new FieldDefinition(
                    name: 'created_at',
                    type: 'integer',
                    label: 'Created at',
                    stored: FieldStorage::Data,
                ),
                'updated_at' => new FieldDefinition(
                    name: 'updated_at',
                    type: 'integer',
                    label: 'Updated at',
                    stored: FieldStorage::Data,
                ),
            ],
        ));

        // GroupType is a config entity (extends ConfigEntityBase). Attribute
        // reflection only applies to ContentEntityBase subclasses, so the
        // config entity registration stays explicit per AD-3 in the plan.
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
            _fieldDefinitions: [
                'description' => new FieldDefinition(
                    name: 'description',
                    type: 'text',
                    label: 'Description',
                    description: 'Human-readable description of this group type.',
                    settings: ['weight' => 5],
                ),
            ],
        ));
    }
}
