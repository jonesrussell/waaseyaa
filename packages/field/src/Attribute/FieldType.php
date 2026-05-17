<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Attribute;

use Waaseyaa\Plugin\Attribute\WaaseyaaPlugin;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class FieldType extends WaaseyaaPlugin
{
    public function __construct(
        string $id,
        string $label = '',
        string $description = '',
        string $package = '',
        public readonly string $category = 'general',
        public readonly int $defaultCardinality = 1,
        public readonly string $defaultWidget = '',
        public readonly string $defaultFormatter = '',
    ) {
        parent::__construct($id, $label, $description, $package);
    }
}
