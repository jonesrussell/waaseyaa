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
     */
    public function __construct(array $values = [])
    {
        $values += [
            'directionality' => 'directed',
            'status' => 1,
        ];

        parent::__construct($values, self::ENTITY_TYPE_ID, self::ENTITY_KEYS);
    }
}
