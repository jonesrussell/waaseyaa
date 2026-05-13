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
    /**
     * @param bool $withoutNewRevision Suppress revision creation on this save.
     * @param ?string $langcode Pin the save to a specific translation langcode (null = active langcode).
     * @param bool $isImport Signal that this save is part of a migration platform write
     *     (see {@see \Waaseyaa\Migration\Plugin\Destination\EntityDestination::write()},
     *     FR-022 of M-002). Subscribers to {@see \Waaseyaa\EntityStorage\Event\BeforeSaveEvent}
     *     / {@see \Waaseyaa\EntityStorage\Event\AfterSaveEvent} may read this flag and
     *     skip expensive non-essential work during bulk imports — e.g. cache
     *     invalidation, search-index refresh, downstream analytics. This is a
     *     passive signal: the coordinator does NOT alter its behaviour based on
     *     this flag; subscribers branch on it themselves.
     */
    private function __construct(
        public readonly bool $withoutNewRevision = false,
        public readonly ?string $langcode = null,
        public readonly bool $isImport = false,
    ) {}

    /**
     * Default context: create a new revision (standard behaviour).
     */
    public static function default(): self
    {
        return new self(withoutNewRevision: false, langcode: null, isImport: false);
    }

    /**
     * Return a new instance with revision creation suppressed.
     *
     * Used for saves that update the current revision in place (e.g. auto-save,
     * status transitions) without cutting a new revision record.
     */
    public function withoutNewRevision(): self
    {
        return new self(
            withoutNewRevision: true,
            langcode: $this->langcode,
            isImport: $this->isImport,
        );
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

        return new self(
            withoutNewRevision: $this->withoutNewRevision,
            langcode: $langcode,
            isImport: $this->isImport,
        );
    }

    /**
     * Return a new instance marking the save as a migration platform import (FR-022).
     *
     * Set by {@see \Waaseyaa\Migration\Plugin\Destination\EntityDestination::write()}.
     * Event subscribers reading the resulting {@see BeforeSaveEvent} or
     * {@see AfterSaveEvent} may inspect `$saveContext->isImport` and branch.
     *
     * @api
     */
    public function asImport(): self
    {
        return new self(
            withoutNewRevision: $this->withoutNewRevision,
            langcode: $this->langcode,
            isImport: true,
        );
    }
}
