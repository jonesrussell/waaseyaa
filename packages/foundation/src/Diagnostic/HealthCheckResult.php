<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Diagnostic;

/**
 * Result of a single health check.
 */
final class HealthCheckResult
{
    public function __construct(
        public readonly string $name,
        public readonly string $status,
        public readonly ?DiagnosticCode $code = null,
        public readonly string $message = '',
        public readonly string $remediation = '',
        public readonly array $context = [],
    ) {}

    public static function pass(string $name, string $message = ''): self
    {
        return new self(name: $name, status: 'pass', message: $message);
    }

    public static function warn(string $name, DiagnosticCode $code, string $message = '', array $context = []): self
    {
        return new self(
            name: $name,
            status: 'warn',
            code: $code,
            message: $message ?: $code->defaultMessage(),
            remediation: $code->remediation(),
            context: $context,
        );
    }

    public static function fail(string $name, DiagnosticCode $code, string $message = '', array $context = []): self
    {
        return new self(
            name: $name,
            status: 'fail',
            code: $code,
            message: $message ?: $code->defaultMessage(),
            remediation: $code->remediation(),
            context: $context,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $result = [
            'name'   => $this->name,
            'status' => $this->status,
        ];

        if ($this->code !== null) {
            $result['code'] = $this->code->value;
        }

        if ($this->message !== '') {
            $result['message'] = $this->message;
        }

        if ($this->remediation !== '') {
            $result['remediation'] = $this->remediation;
        }

        if ($this->context !== []) {
            $result['context'] = $this->context;
        }

        return $result;
    }
}
