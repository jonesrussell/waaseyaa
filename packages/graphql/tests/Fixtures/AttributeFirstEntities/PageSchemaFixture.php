<?php

declare(strict_types=1);

namespace Waaseyaa\GraphQL\Tests\Fixtures\AttributeFirstEntities;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;

/**
 * Minimal page fixture used by GraphQlEndpointTest and the SchemaFactory
 * cache-key test.
 */
#[ContentEntityType(id: 'page', label: 'Page')]
#[ContentEntityKeys(id: 'id')]
final class PageSchemaFixture extends ContentEntityBase
{
    #[Field]
    public ?int $id = null;

    #[Field(required: true)]
    public string $title = '';

    #[Field(type: 'text')]
    public string $body = '';
}
