<?php

declare(strict_types=1);

namespace Waaseyaa\Messaging;

use Waaseyaa\Entity\ContentEntityBase;

final class ThreadParticipant extends ContentEntityBase
{
    protected string $entityTypeId = 'thread_participant';

    protected array $entityKeys = [
        'id' => 'tpid',
        'uuid' => 'uuid',
        'label' => 'role',
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
        foreach (['thread_id', 'user_id', 'thread_creator_id'] as $field) {
            if (!isset($values[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!array_key_exists('role', $values)) {
            $values['role'] = 'member';
        }
        if (!in_array((string) $values['role'], ['owner', 'member'], true)) {
            throw new \InvalidArgumentException('Invalid role: ' . (string) $values['role']);
        }
        if (!array_key_exists('joined_at', $values)) {
            $values['joined_at'] = time();
        }
        if (!array_key_exists('last_read_at', $values)) {
            $values['last_read_at'] = 0;
        }

        $entityTypeId = $entityTypeId !== '' ? $entityTypeId : $this->entityTypeId;
        $entityKeys = $entityKeys !== [] ? $entityKeys : $this->entityKeys;

        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }
}
