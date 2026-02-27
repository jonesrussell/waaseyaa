<?php

declare(strict_types=1);

namespace Aurora\TypedData;

use Symfony\Component\Validator\Constraint;

interface DataDefinitionInterface
{
    public function getDataType(): string;

    public function getLabel(): string;

    public function getDescription(): string;

    public function isRequired(): bool;

    public function isReadOnly(): bool;

    public function isList(): bool;

    /** @return Constraint[] */
    public function getConstraints(): array;
}
