<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Attribute;

/**
 * Marks a public typed property of a content entity class as a persistable field.
 *
 * When `type:` is null, the field type id is inferred from the PHP property type
 * by {@see FieldTypeInferrer}. When `type:` is set explicitly it must be one of
 * the registered field-type ids (and must be compatible with the declared PHP
 * property type, when one is present).
 *
 * The attribute is data-only — all parameters are exposed as public readonly
 * properties; logic lives in `FieldTypeInferrer` and `EntityMetadataReader`.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Field
{
    /**
     * @param array<string, mixed> $settings Arbitrary settings; merged with type-inferred settings (e.g. enum_class).
     */
    public function __construct(
        public ?string $type = null,
        public ?bool $required = null,
        public mixed $default = null,
        public string $label = '',
        public string $description = '',
        public array $settings = [],
        public bool $readOnly = false,
        public bool $translatable = false,
        public bool $revisionable = false,
    ) {}
}
