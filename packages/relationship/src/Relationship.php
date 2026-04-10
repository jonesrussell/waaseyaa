<?php

declare(strict_types=1);

namespace Waaseyaa\Relationship;

use Waaseyaa\Entity\ContentEntityBase;

final class Relationship extends ContentEntityBase
{
    private const ENTITY_TYPE_ID = 'relationship';

    /** @var array<string, string> */
    private const ENTITY_KEYS = [
        'id' => 'rid',
        'uuid' => 'uuid',
        'label' => 'relationship_type',
        'bundle' => 'relationship_type',
    ];

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $entityKeys Explicit keys when reconstructing via {@see ContentEntityBase::duplicateInstance()}.
     */
    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        $values += [
            'directionality' => 'directed',
            'status' => 1,
        ];

        $entityTypeId = $entityTypeId !== '' ? $entityTypeId : self::ENTITY_TYPE_ID;
        $entityKeys = $entityKeys !== [] ? $entityKeys : self::ENTITY_KEYS;

        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }
}
