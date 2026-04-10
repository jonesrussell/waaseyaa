<?php

declare(strict_types=1);

namespace Waaseyaa\Engagement;

use Waaseyaa\Entity\ContentEntityBase;

final class Reaction extends ContentEntityBase
{
    protected string $entityTypeId = 'reaction';

    protected array $entityKeys = [
        'id' => 'rid',
        'uuid' => 'uuid',
        'label' => 'reaction_type',
    ];

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $entityKeys Explicit keys when reconstructing via {@see ContentEntityBase::duplicateInstance()}.
     * @param list<string>|null $allowedReactionTypes Allowed types (null = accept any non-empty string)
     */
    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
        ?array $allowedReactionTypes = null,
    ) {
        foreach (['user_id', 'target_type', 'target_id', 'reaction_type'] as $field) {
            if (!isset($values[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if ($allowedReactionTypes !== null && !in_array($values['reaction_type'], $allowedReactionTypes, true)) {
            throw new \InvalidArgumentException(
                "Invalid reaction_type '{$values['reaction_type']}'. Allowed: " . implode(', ', $allowedReactionTypes),
            );
        }

        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = time();
        }

        $entityTypeId = $entityTypeId !== '' ? $entityTypeId : $this->entityTypeId;
        $entityKeys = $entityKeys !== [] ? $entityKeys : $this->entityKeys;

        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }
}
