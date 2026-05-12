<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Integration\BehaviorIdentity;

use Waaseyaa\Entity\ContentEntityBase;

/**
 * Minimal open-schema entity fixture for behavior-identity tests.
 *
 * Accepts arbitrary values so both the baseline and post-refactor tests
 * can store ad-hoc fields without registering field definitions.
 */
final class BehaviorIdentityEntity extends ContentEntityBase
{
    public function __construct(
        array $values = [],
        string $entityTypeId = 'bi_entity',
        array $entityKeys = [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'label',
            'bundle' => 'bundle',
            'langcode' => 'langcode',
        ],
        array $fieldDefinitions = [],
    ) {
        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }
}
