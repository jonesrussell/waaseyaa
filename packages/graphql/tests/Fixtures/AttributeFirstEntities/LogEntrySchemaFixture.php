<?php

declare(strict_types=1);

namespace Waaseyaa\GraphQL\Tests\Fixtures\AttributeFirstEntities;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;

/**
 * Log-entry fixture used to assert the schema generator excludes readOnly
 * fields from update / create input types.
 */
#[ContentEntityType(id: 'log_entry', label: 'Log Entry')]
#[ContentEntityKeys(id: 'id')]
final class LogEntrySchemaFixture extends ContentEntityBase
{
    #[Field(readOnly: true)]
    public ?int $id = null;

    #[Field(required: true)]
    public string $message = '';

    #[Field(readOnly: true)]
    public ?\DateTimeImmutable $timestamp = null;
}
