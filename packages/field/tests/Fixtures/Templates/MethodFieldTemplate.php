<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Tests\Fixtures\Templates;

use Waaseyaa\Field\Attribute\BundleTemplate;
use Waaseyaa\Field\Attribute\FieldTemplate;

/**
 * Template with two property fields and one method field.
 * Properties should appear first in declaration order, then the method.
 */
#[BundleTemplate(entityType: 'node', bundle: 'page')]
final class MethodFieldTemplate
{
    #[FieldTemplate(key: 'prop_a', type: 'string', label: 'Property A')]
    public string $propA = '';

    #[FieldTemplate(key: 'prop_b', type: 'string', label: 'Property B')]
    public string $propB = '';

    #[FieldTemplate(key: 'method_c', type: 'integer', label: 'Method C')]
    public function methodC(): void {}
}
