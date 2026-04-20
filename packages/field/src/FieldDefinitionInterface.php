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
}
