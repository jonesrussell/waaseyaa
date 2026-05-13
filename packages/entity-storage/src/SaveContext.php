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
 * $ctx = $ctx->withLangcode('fr');   // pin save to a non-active translation
 * ```
 */
final class SaveContext
{
    private function __construct(
        public readonly bool $withoutNewRevision = false,
        public readonly ?string $langcode = null,
    ) {}

    /**
     * Default context: create a new revision (standard behaviour).
     */
    public static function default(): self
    {
        return new self(withoutNewRevision: false, langcode: null);
    }

    /**
     * Return a new instance with revision creation suppressed.
     *
     * Used for saves that update the current revision in place (e.g. auto-save,
     * status transitions) without cutting a new revision record.
     */
    public function withoutNewRevision(): self
    {
        return new self(withoutNewRevision: true, langcode: $this->langcode);
    }

    /**
     * Return a new instance pinning the save to a specific translation langcode.
     *
     * When set, the storage coordinator writes the entity's data for this
     * langcode rather than {@see TranslatableInterface::activeLangcode()}.
     * Empty strings are rejected.
     */
    public function withLangcode(string $langcode): self
    {
        if ($langcode === '') {
            throw new \InvalidArgumentException('SaveContext::withLangcode requires a non-empty langcode.');
        }

        return new self(withoutNewRevision: $this->withoutNewRevision, langcode: $langcode);
    }
}
