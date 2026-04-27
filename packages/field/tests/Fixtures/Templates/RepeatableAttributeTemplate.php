<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Tests\Fixtures\Templates;

use Waaseyaa\Field\Attribute\BundleTemplate;
use Waaseyaa\Field\Attribute\FieldTemplate;

/**
 * Template demonstrating two #[FieldTemplate] attributes on a single property.
 */
#[BundleTemplate(entityType: 'node', bundle: 'event')]
final class RepeatableAttributeTemplate
{
    #[FieldTemplate(key: 'start_date', type: 'string', label: 'Start Date')]
    #[FieldTemplate(key: 'end_date', type: 'string', label: 'End Date')]
    public string $dates = '';
}
