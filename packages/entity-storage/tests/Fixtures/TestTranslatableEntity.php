<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Fixtures;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\ContentEntityBase;

/**
 * Fixture content entity for the translatable contract suite (WP13).
 *
 * The entity type id is `test_translatable_entity`. Two translatable fields
 * (`title`, `body`) plus a deliberately-unset translatable `description` (used
 * by T12 fallback-exhaustion coverage), and two non-translatable fields
 * (`created_at`, `author_id`) used to verify NFR-003 reference-equality across
 * translation handles.
 *
 * @internal Test fixture — registered via {@see TestTranslatableEntityTypeFactory}.
 */
#[ContentEntityType(id: 'test_translatable_entity')]
#[ContentEntityKeys(
    id: 'id',
    uuid: 'uuid',
    bundle: 'bundle',
    label: 'label',
    langcode: 'langcode',
    default_langcode: 'default_langcode',
)]
class TestTranslatableEntity extends ContentEntityBase
{
    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }
}
