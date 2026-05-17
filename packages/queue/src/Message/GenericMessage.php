<?php

declare(strict_types=1);

namespace Waaseyaa\Queue\Message;

/**
 * @api
 */
final readonly class GenericMessage
{
    public function __construct(
        public string $type,
        public array $payload = [],
    ) {}
}
