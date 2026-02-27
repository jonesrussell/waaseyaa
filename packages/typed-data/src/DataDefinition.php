<?php

declare(strict_types=1);

namespace Aurora\TypedData;

use Symfony\Component\Validator\Constraint;

final readonly class DataDefinition implements DataDefinitionInterface
{
    /**
     * @param Constraint[] $constraints
     */
    public function __construct(
        private string $dataType,
        private string $label = '',
        private string $description = '',
        private bool $required = false,
        private bool $readOnly = false,
        private bool $isList = false,
        private array $constraints = [],
    ) {}

    public function getDataType(): string
    {
        return $this->dataType;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function isList(): bool
    {
        return $this->isList;
    }

    /** @return Constraint[] */
    public function getConstraints(): array
    {
        return $this->constraints;
    }
}
