<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Fixtures\AttributeFirstEntities;

use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;

/**
 * Fixture used by EntityTypeValidationConstraintsTest. The `title` property
 * is marked required, which causes the validation-constraint compiler to
 * derive a NotBlank constraint. Tests then attach manual constraints to
 * verify replace/augment semantics for derived vs. manual constraints.
 */
#[ContentEntityType(id: 'validation_constraints_fixture', label: 'X')]
final class ConstraintsRequiredTitleFixture extends ContentEntityBase
{
    #[Field(required: true)]
    public string $title = '';
}
