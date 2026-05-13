<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Attribute;

/**
 * Declares how logical entity keys (id, uuid, label, …) map to storage field names
 * for a content entity class. Omitted or null parameters are filled from
 * {@see EntityMetadataReader} (identity defaults and inheritance merge).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class ContentEntityKeys
{
    public function __construct(
        public ?string $id = null,
        public ?string $uuid = null,
        public ?string $label = null,
        public ?string $bundle = null,
        public ?string $revision = null,
        public ?string $langcode = null,
        public ?string $default_langcode = null,
    ) {}
}
