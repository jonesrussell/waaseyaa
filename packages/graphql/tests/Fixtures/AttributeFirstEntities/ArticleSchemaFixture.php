<?php

declare(strict_types=1);

namespace Waaseyaa\GraphQL\Tests\Fixtures\AttributeFirstEntities;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;

/**
 * Article fixture used by SchemaFactoryTest, SchemaValidationTest, and
 * EntityResolverTest. Properties model the field shape consumed by the
 * GraphQL schema generator: required vs. optional, integer/string/boolean/
 * float/timestamp scalars, and a couple of references for input-type tests.
 */
#[ContentEntityType(id: 'article', label: 'Article')]
#[ContentEntityKeys(id: 'id', uuid: 'uuid')]
final class ArticleSchemaFixture extends ContentEntityBase
{
    #[Field]
    public ?int $id = null;

    #[Field]
    public string $uuid = '';

    #[Field(required: true)]
    public string $title = '';

    #[Field(type: 'text', required: false)]
    public string $body = '';

    #[Field]
    public bool $status = false;

    #[Field]
    public ?\DateTimeImmutable $created = null;

    #[Field]
    public ?int $view_count = null;

    #[Field]
    public ?float $rating = null;
}
