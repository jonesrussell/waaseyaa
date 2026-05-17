<?php

declare(strict_types=1);

namespace Waaseyaa\Ingestion;

/**
 * Immutable result of a payload validation.
 *
 * Contains a list of error messages (empty = valid).
 * @api
 */
final readonly class ValidationResult
{
    /** @param list<string> $errors */
    public function __construct(
        public array $errors = [],
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
