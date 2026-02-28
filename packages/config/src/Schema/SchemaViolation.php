<?php

declare(strict_types=1);

namespace Aurora\Config\Schema;

/**
 * Represents a single config schema validation violation.
 */
final readonly class SchemaViolation
{
    public function __construct(
        /** The dot-separated path to the offending property. */
        public string $path,
        /** A human-readable description of the violation. */
        public string $message,
    ) {}

    public function __toString(): string
    {
        return sprintf('[%s] %s', $this->path, $this->message);
    }
}
