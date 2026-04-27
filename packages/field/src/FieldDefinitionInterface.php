<?php

declare(strict_types=1);

namespace Waaseyaa\Field;

use Waaseyaa\TypedData\DataDefinitionInterface;

interface FieldDefinitionInterface extends DataDefinitionInterface
{
    public function getName(): string;

    public function getType(): string;

    public function getCardinality(): int;

    public function isMultiple(): bool;

    public function getSettings(): array;

    public function getSetting(string $name): mixed;

    public function getTargetEntityTypeId(): string;

    public function getTargetBundle(): ?string;

    public function isTranslatable(): bool;

    public function isRevisionable(): bool;

    public function getDefaultValue(): mixed;

    public function toJsonSchema(): array;

    public function getStored(): FieldStorage;

    /**
     * The optional group key used by the form descriptor builder for grouping fields visually.
     * Empty string means "no group".
     */
    public function getGroup(): string;

    /**
     * Optional prompt aliases used by the structured-import pipeline for fuzzy-tolerant matching.
     * Empty list means "match by field name only".
     *
     * @return list<string>
     */
    public function getPromptAliases(): array;
}
