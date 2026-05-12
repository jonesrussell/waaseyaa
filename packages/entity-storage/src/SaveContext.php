<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage;

/**
 * @api
 *
 * Immutable value object carrying flags for a single save operation.
 *
 * Resolves open question Q6 (research §3): dedicated value object, not a flags
 * array on {@see EntityStorageCoordinator::write()}. Future flags (e.g.
 * withoutEvents, skipValidation) extend this object without changing call sites.
 *
 * ## Usage
 * ```php
 * $ctx = SaveContext::default();
 * $ctx = $ctx->withoutNewRevision(); // returns NEW instance; original unchanged
 * ```
 */
final class SaveContext
{
    private function __construct(
        public readonly bool $withoutNewRevision = false,
    ) {}

    /**
     * Default context: create a new revision (standard behaviour).
     */
    public static function default(): self
    {
        return new self(withoutNewRevision: false);
    }

    /**
     * Return a new instance with revision creation suppressed.
     *
     * Used for saves that update the current revision in place (e.g. auto-save,
     * status transitions) without cutting a new revision record.
     */
    public function withoutNewRevision(): self
    {
        return new self(withoutNewRevision: true);
    }
}
