<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Exception;

final class ConfigException extends WaaseyaaException
{
    public function __construct(
        string $message,
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 'aurora:config/error', 500, $context, $previous);
    }
}
