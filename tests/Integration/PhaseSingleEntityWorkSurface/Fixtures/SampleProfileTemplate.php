<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\PhaseSingleEntityWorkSurface\Fixtures;

use Waaseyaa\Field\Attribute\BundleTemplate;
use Waaseyaa\Field\Attribute\FieldTemplate;

/**
 * Fixture bundle template for the (node, profile) bundle.
 *
 * Exercises all FieldTemplate features: groups, prompt aliases, required flag.
 * Used by the end-to-end integration test (T044 / Success Criterion 5).
 */
#[BundleTemplate(entityType: 'node', bundle: 'profile')]
final class SampleProfileTemplate
{
    #[FieldTemplate(
        key: 'name',
        type: 'string',
        label: 'Display Name',
        group: 'identity',
        promptAliases: ['name', 'display name', 'full name'],
        required: true,
    )]
    public string $name = '';

    #[FieldTemplate(
        key: 'bio',
        type: 'text',
        label: 'Biography',
        group: 'about',
        promptAliases: ['bio', 'biography', 'about'],
    )]
    public string $bio = '';

    #[FieldTemplate(
        key: 'birthplace',
        type: 'string',
        label: 'Born In',
        group: 'about',
        promptAliases: ['birthplace', 'born in', 'hometown'],
    )]
    public string $birthplace = '';

    #[FieldTemplate(
        key: 'website',
        type: 'string',
        label: 'Website',
        group: 'contact',
        promptAliases: ['website', 'url', 'web address'],
    )]
    public string $website = '';

    #[FieldTemplate(
        key: 'is_published',
        type: 'boolean',
        label: 'Status',
        group: 'publishing',
        promptAliases: ['is_published', 'published', 'active'],
    )]
    public bool $isPublished = false;
}
