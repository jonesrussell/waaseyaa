<?php

declare(strict_types=1);

namespace Waaseyaa\TypedData;

use Waaseyaa\TypedData\Type\BooleanData;
use Waaseyaa\TypedData\Type\FloatData;
use Waaseyaa\TypedData\Type\IntegerData;
use Waaseyaa\TypedData\Type\ListData;
use Waaseyaa\TypedData\Type\MapData;
use Waaseyaa\TypedData\Type\StringData;

/**
 * @api
 */
final class TypedDataManager implements TypedDataManagerInterface
{
    /** @var array<string, class-string> */
    private array $typeMap = [];

    public function __construct()
    {
        $this->typeMap = [
            'string' => StringData::class,
            'integer' => IntegerData::class,
            'boolean' => BooleanData::class,
            'float' => FloatData::class,
            'list' => ListData::class,
            'map' => MapData::class,
        ];
    }

    public function createDataDefinition(string $dataType): DataDefinitionInterface
    {
        if (!isset($this->typeMap[$dataType])) {
            throw new \InvalidArgumentException(sprintf('Unknown data type "%s".', $dataType));
        }

        return new DataDefinition(dataType: $dataType);
    }

    public function create(DataDefinitionInterface $definition, mixed $value = null, array $options = []): TypedDataInterface
    {
        $dataType = $definition->getDataType();

        if (!isset($this->typeMap[$dataType])) {
            throw new \InvalidArgumentException(sprintf('Unknown data type "%s".', $dataType));
        }

        $class = $this->typeMap[$dataType];

        $instance = match ($dataType) {
            'list' => new $class($definition, $this, $options['item_type'] ?? 'string'),
            'map' => new $class($definition, $this),
            default => new $class($definition, $value),
        };

        if ($value !== null && ($dataType === 'list' || $dataType === 'map')) {
            $instance->setValue($value);
        }

        return $instance;
    }

    public function createInstance(string $dataType, array $configuration = []): TypedDataInterface
    {
        $definition = new DataDefinition(
            dataType: $dataType,
            label: $configuration['label'] ?? '',
            description: $configuration['description'] ?? '',
            required: $configuration['required'] ?? false,
            readOnly: $configuration['read_only'] ?? false,
            isList: $configuration['is_list'] ?? false,
            constraints: $configuration['constraints'] ?? [],
        );

        $value = $configuration['value'] ?? null;
        $options = [];
        if (isset($configuration['item_type'])) {
            $options['item_type'] = $configuration['item_type'];
        }

        return $this->create($definition, $value, $options);
    }

    /** @return array<string, DataDefinitionInterface> */
    public function getDefinitions(): array
    {
        $definitions = [];
        foreach (array_keys($this->typeMap) as $type) {
            $definitions[$type] = new DataDefinition(dataType: $type);
        }

        return $definitions;
    }
}
