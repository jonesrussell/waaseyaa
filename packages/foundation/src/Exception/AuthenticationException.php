<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Exception;

final class AuthenticationException extends WaaseyaaException
{
    public function __construct(
        string $message,
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 'waaseyaa:auth/error', 401, $context, $previous);
    }
}
