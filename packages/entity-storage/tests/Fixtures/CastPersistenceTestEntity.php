<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Fixtures;

use Waaseyaa\Entity\ContentEntityBase;

/**
 * Content entity with $casts for repository/storage persistence tests (#1181).
 */
class CastPersistenceTestEntity extends ContentEntityBase
{
    /**
     * @var array<string, string|array<string, mixed>>
     */
    protected array $casts = [
        'score' => 'int',
        'tags' => 'array',
        'mode' => CastPersistenceStringEnum::class,
    ];

    public function __construct(
        array $values = [],
        string $entityTypeId = 'cast_persist_entity',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }
}
