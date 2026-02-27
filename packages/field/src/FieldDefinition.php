<?php

declare(strict_types=1);

namespace Aurora\Field;

use Symfony\Component\Validator\Constraint;

final readonly class FieldDefinition implements FieldDefinitionInterface
{
    /**
     * @param array<string, mixed> $settings
     * @param Constraint[] $constraints
     */
    public function __construct(
        private string $name,
        private string $type,
        private int $cardinality = 1,
        private array $settings = [],
        private string $targetEntityTypeId = '',
        private ?string $targetBundle = null,
        private bool $translatable = false,
        private bool $revisionable = false,
        private mixed $defaultValue = null,
        private string $label = '',
        private string $description = '',
        private bool $required = false,
        private bool $readOnly = false,
        private array $constraints = [],
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getCardinality(): int
    {
        return $this->cardinality;
    }

    public function isMultiple(): bool
    {
        return $this->cardinality !== 1;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getSetting(string $name): mixed
    {
        return $this->settings[$name] ?? null;
    }

    public function getTargetEntityTypeId(): string
    {
        return $this->targetEntityTypeId;
    }

    public function getTargetBundle(): ?string
    {
        return $this->targetBundle;
    }

    public function isTranslatable(): bool
    {
        return $this->translatable;
    }

    public function isRevisionable(): bool
    {
        return $this->revisionable;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function toJsonSchema(): array
    {
        $schema = match ($this->type) {
            'string' => ['type' => 'string'],
            'integer' => ['type' => 'integer'],
            'boolean' => ['type' => 'boolean'],
            'float' => ['type' => 'number'],
            'text' => [
                'type' => 'object',
                'properties' => [
                    'value' => ['type' => 'string'],
                    'format' => ['type' => 'string'],
                ],
            ],
            'entity_reference' => [
                'type' => 'object',
                'properties' => [
                    'target_id' => ['type' => 'integer'],
                    'target_type' => ['type' => 'string'],
                ],
            ],
            default => ['type' => 'string'],
        };

        if ($this->isMultiple()) {
            return [
                'type' => 'array',
                'items' => $schema,
            ];
        }

        return $schema;
    }

    // DataDefinitionInterface methods

    public function getDataType(): string
    {
        return $this->type;
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
        return $this->isMultiple();
    }

    /** @return Constraint[] */
    public function getConstraints(): array
    {
        return $this->constraints;
    }
}
