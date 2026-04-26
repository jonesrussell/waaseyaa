<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\GraphQL\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\ContentEntityBase;

#[ContentEntityType(id: 'organization')]
#[ContentEntityKeys(id: 'id', uuid: 'uuid', label: 'name')]
final class TestOrganization extends ContentEntityBase
{
    public function __construct(
        array $values = [],
        string $entityTypeId = 'organization',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }
}
