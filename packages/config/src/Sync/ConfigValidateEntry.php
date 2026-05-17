<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Sync;

/**
 * One per-entity validation entry surfaced by {@see ConfigSyncValidator}.
 *
 * An entry with zero violations is "OK" — the CLI renders it as
 * `<ref>: OK` per `contracts/cli-namespace.md §config:validate` (FR-039).
 *
 * @api
 */
final readonly class ConfigValidateEntry
{
    /**
     * @param list<FieldViolation> $violations
     */
    public function __construct(
        public string $ref,
        public array $violations,
    ) {}

    public function isValid(): bool
    {
        return $this->violations === [];
    }
}
