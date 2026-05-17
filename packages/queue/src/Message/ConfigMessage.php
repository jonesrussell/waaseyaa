<?php

declare(strict_types=1);

namespace Waaseyaa\Queue\Message;

/**
 * @api
 */
final readonly class ConfigMessage
{
    public function __construct(
        public string $configName,
        public string $operation,
        public array $data = [],
    ) {}
}
