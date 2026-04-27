<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Tests\Fixtures\Templates;

use Waaseyaa\Field\Attribute\BundleTemplate;
use Waaseyaa\Field\Attribute\FieldTemplate;

/**
 * Template with duplicate normalized prompt aliases — used to verify exception is thrown.
 * 'Headline' and 'headline' normalize to the same string.
 */
#[BundleTemplate(entityType: 'node', bundle: 'alias_dup')]
final class DuplicateAliasTemplate
{
    #[FieldTemplate(key: 'field_a', type: 'string', label: 'Field A', promptAliases: ['Headline'])]
    public string $fieldA = '';

    #[FieldTemplate(key: 'field_b', type: 'string', label: 'Field B', promptAliases: ['headline'])]
    public string $fieldB = '';
}
