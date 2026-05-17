<?php

declare(strict_types=1);

namespace Waaseyaa\Queue\Message;

/**
 * @api
 */
final readonly class EntityMessage
{
    public function __construct(
        public string $entityTypeId,
        public int|string $entityId,
        public string $operation,
        public array $data = [],
    ) {}
}
