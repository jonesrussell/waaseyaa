<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Backend;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\ContentEntityBase;

/**
 * @internal Test fixture for {@see SqlColumnTranslatableTest} and related coordinator tests.
 *
 * Lives in its own file so PSR-4 autoloading can resolve the class without relying on
 * the parent test file being eagerly required (which is not guaranteed when this fixture
 * is referenced from another test class — see #1457).
 */
#[ContentEntityType(id: 'sqlcol_article')]
#[ContentEntityKeys(
    id: 'id',
    uuid: 'uuid',
    bundle: 'bundle',
    label: 'label',
    langcode: 'langcode',
    default_langcode: 'default_langcode',
)]
final class SqlColumnTranslatableArticleFixture extends ContentEntityBase
{
    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }
}
