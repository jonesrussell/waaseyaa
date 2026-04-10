<?php

declare(strict_types=1);

namespace Waaseyaa\Testing\Tests\Fixture;

use Waaseyaa\Entity\ContentEntityBase;

/**
 * @internal Minimal fieldable content entity for {@see EntityType} class pointers in tests.
 */
final class StubFieldableEntity extends ContentEntityBase
{
    public function __construct(array $values = [])
    {
        parent::__construct(
            values: $values,
            entityTypeId: 'stub_fieldable',
            entityKeys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'label' => 'title',
                'bundle' => 'type',
                'langcode' => 'langcode',
            ],
        );
    }
}
