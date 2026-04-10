<?php

declare(strict_types=1);

namespace Waaseyaa\Messaging;

use Waaseyaa\Entity\ContentEntityBase;

final class ThreadMessage extends ContentEntityBase
{
    protected string $entityTypeId = 'thread_message';

    protected array $entityKeys = [
        'id' => 'tmid',
        'uuid' => 'uuid',
        'label' => 'body',
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
        foreach (['thread_id', 'sender_id', 'body'] as $field) {
            if (!isset($values[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        $body = trim((string) $values['body']);
        if ($body === '' || mb_strlen($body) > 2000) {
            throw new \InvalidArgumentException('Body must be 1-2000 characters');
        }

        $values['body'] = $body;
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = time();
        }
        if (!array_key_exists('edited_at', $values)) {
            $values['edited_at'] = null;
        }
        if (!array_key_exists('deleted_at', $values)) {
            $values['deleted_at'] = null;
        }

        $entityTypeId = $entityTypeId !== '' ? $entityTypeId : $this->entityTypeId;
        $entityKeys = $entityKeys !== [] ? $entityKeys : $this->entityKeys;

        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }
}
