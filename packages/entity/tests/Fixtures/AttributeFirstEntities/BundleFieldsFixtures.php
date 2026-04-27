<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Fixtures\AttributeFirstEntities;

use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;

/**
 * Multi-bundle group fixture used by EntityTypeManagerBundleFieldsTest to
 * exercise core-field registry registration in the attribute-first form.
 *
 * Bundle-scoped fields (e.g. business-only "email") still flow through the
 * imperative {@see EntityTypeManager::addBundleFields()} API, since
 * bundle-scoped attributes are deferred to a follow-on mission.
 */
#[ContentEntityType(id: 'group', label: 'Group')]
final class GroupBundleFixture extends ContentEntityBase
{
    #[Field(label: 'Name')]
    public string $name = '';
}
