<?php

declare(strict_types=1);

namespace Waaseyaa\Entity;

interface EntityTypeInterface
{
    public function id(): string;

    public function getLabel(): string;

    public function getClass(): string;

    /** @return class-string<Storage\EntityStorageInterface> */
    public function getStorageClass(): string;

    /** @return array<string, string> Entity keys (id, uuid, label, bundle, revision, langcode, etc.) */
    public function getKeys(): array;

    public function isRevisionable(): bool;

    public function getRevisionDefault(): bool;

    public function isTranslatable(): bool;

    public function getBundleEntityType(): ?string;

    /** @return array<string, mixed> */
    public function getConstraints(): array;

    /**
     * Field definitions keyed by field name.
     *
     * Prefer {@see \Waaseyaa\Field\FieldDefinitionInterface} instances; associative
     * metadata arrays remain supported during alpha only (see docs/specs/entity-system.md,
     * "Breaking-change cutover (alpha → stable)").
     *
     * @return array<string, array<string, mixed>|\Waaseyaa\Field\FieldDefinitionInterface>
     */
    public function getFieldDefinitions(): array;

    /** @return string|null Admin sidebar group key (e.g. 'content', 'taxonomy'). */
    public function getGroup(): ?string;

    /** @return string|null Human-readable description of the entity type. */
    public function getDescription(): ?string;
}
