<?php

declare(strict_types=1);

namespace Waaseyaa\Messaging;

use Waaseyaa\Entity\ContentEntityBase;

final class MessageThread extends ContentEntityBase
{
    protected string $entityTypeId = 'message_thread';

    protected array $entityKeys = [
        'id' => 'mtid',
        'uuid' => 'uuid',
        'label' => 'title',
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
        if (!isset($values['created_by'])) {
            throw new \InvalidArgumentException('Missing required field: created_by');
        }

        if (!array_key_exists('title', $values)) {
            $values['title'] = '';
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = time();
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = $values['created_at'];
        }
        if (!array_key_exists('thread_type', $values)) {
            $values['thread_type'] = 'direct';
        }
        if (!in_array($values['thread_type'], ['direct', 'group'], true)) {
            throw new \InvalidArgumentException('thread_type must be direct or group');
        }
        if (!array_key_exists('last_message_at', $values)) {
            $values['last_message_at'] = $values['created_at'];
        }

        $entityTypeId = $entityTypeId !== '' ? $entityTypeId : $this->entityTypeId;
        $entityKeys = $entityKeys !== [] ? $entityKeys : $this->entityKeys;

        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }
}
