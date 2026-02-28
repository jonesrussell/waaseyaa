<?php

declare(strict_types=1);

namespace Aurora\Api\Tests\Fixtures;

use Aurora\Entity\ContentEntityBase;

/**
 * Simple test entity for API tests.
 */
class TestEntity extends ContentEntityBase
{
    public function __construct(
        array $values = [],
        string $entityTypeId = 'article',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        $defaultKeys = [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'title',
            'bundle' => 'type',
            'langcode' => 'langcode',
        ];

        parent::__construct(
            $values,
            $entityTypeId,
            $entityKeys !== [] ? $entityKeys : $defaultKeys,
            $fieldDefinitions,
        );
    }
}
