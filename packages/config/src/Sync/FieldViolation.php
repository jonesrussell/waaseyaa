<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Sync;

/**
 * One field-level validation violation surfaced by
 * {@see ConfigSyncValidator}.
 *
 * Stability scope (charter §5.5): the three-field shape (`field`, `message`,
 * `code`) is on stable surface for `waaseyaa/config` v1.x; additional
 * optional context may be added between minor versions.
 *
 * `code` is a stable, dot-separated identifier (e.g. `meta.parse`,
 * `fields.empty`, `fields.required`) intended for CI-side filtering;
 * `message` is human-readable and may evolve between patch versions.
 *
 * @api
 */
final readonly class FieldViolation
{
    public function __construct(
        public string $field,
        public string $message,
        public string $code,
    ) {}
}
