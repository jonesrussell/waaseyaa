<?php

declare(strict_types=1);

namespace Waaseyaa\Testing\Tests\Fixture;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\ContentEntityBase;

/**
 * @internal Minimal fieldable content entity for {@see EntityType} class pointers in tests.
 */
#[ContentEntityType(id: 'stub_fieldable')]
#[ContentEntityKeys(id: 'id', uuid: 'uuid', label: 'title', bundle: 'type', langcode: 'langcode')]
final class StubFieldableEntity extends ContentEntityBase
{
    public function __construct(array $values = [])
    {
        parent::__construct($values);
    }
}
