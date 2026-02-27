<?php

declare(strict_types=1);

namespace Aurora\TypedData;

interface TypedDataManagerInterface
{
    public function createDataDefinition(string $dataType): DataDefinitionInterface;

    public function create(DataDefinitionInterface $definition, mixed $value = null): TypedDataInterface;

    public function createInstance(string $dataType, array $configuration = []): TypedDataInterface;

    /** @return array<string, DataDefinitionInterface> */
    public function getDefinitions(): array;
}
