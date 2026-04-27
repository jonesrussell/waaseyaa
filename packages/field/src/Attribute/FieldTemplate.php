<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class FieldTemplate
{
    /** @param list<string> $promptAliases */
    public function __construct(
        public string $key,
        public string $type,
        public string $label = '',
        public string $group = '',
        public array $promptAliases = [],
        public bool $required = false,
        public bool $readOnly = false,
    ) {}
}
