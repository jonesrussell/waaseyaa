<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Fixtures;

use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\RevisionableEntityInterface;
use Waaseyaa\Entity\RevisionableEntityTrait;

/**
 * Minimal revisionable entity fixture for WP08 integration tests.
 *
 * Implements {@see RevisionableEntityInterface} (the WP07/WP08 contract),
 * not the legacy RevisionableInterface.
 */
final class RevisionableArticleEntity extends ContentEntityBase implements RevisionableEntityInterface
{
    use RevisionableEntityTrait;

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $entityKeys
     * @param array<mixed>          $fieldDefinitions
     */
    public function __construct(
        array $values = [],
        string $entityTypeId = 'article',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        parent::__construct(
            $values,
            $entityTypeId ?: 'article',
            $entityKeys ?: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title', 'revision' => 'vid'],
            $fieldDefinitions,
        );
    }
}
