<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Tests\Fixtures\Templates;

use Waaseyaa\Field\Attribute\BundleTemplate;
use Waaseyaa\Field\Attribute\FieldTemplate;

/**
 * Template with a duplicate field key — used to verify exception is thrown.
 */
#[BundleTemplate(entityType: 'node', bundle: 'duplicate')]
final class DuplicateKeyTemplate
{
    #[FieldTemplate(key: 'title', type: 'string', label: 'Title')]
    public string $title = '';

    #[FieldTemplate(key: 'title', type: 'text', label: 'Title Again')]
    public string $titleAgain = '';
}
