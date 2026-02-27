<?php

declare(strict_types=1);

namespace Aurora\TypedData;

use Symfony\Component\Validator\ConstraintViolationListInterface;

interface TypedDataInterface
{
    public function getValue(): mixed;

    public function setValue(mixed $value): void;

    public function getDataDefinition(): DataDefinitionInterface;

    public function validate(): ConstraintViolationListInterface;

    public function getString(): string;
}
