<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Tests\Fixtures\Templates;

use Waaseyaa\Field\Attribute\BundleTemplate;
use Waaseyaa\Field\Attribute\FieldTemplate;

#[BundleTemplate(entityType: 'node', bundle: 'article')]
final class SampleArticleTemplate
{
    #[FieldTemplate(key: 'title', type: 'string', label: 'Title', group: 'basic', promptAliases: ['headline', 'subject'], required: true)]
    public string $title = '';

    #[FieldTemplate(key: 'body', type: 'text', label: 'Body', group: 'content', promptAliases: ['content', 'text'])]
    public string $body = '';

    #[FieldTemplate(key: 'tags', type: 'string', label: 'Tags', group: 'metadata', promptAliases: ['keywords', 'labels'])]
    public string $tags = '';
}
