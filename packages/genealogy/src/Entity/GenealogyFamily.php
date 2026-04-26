<?php

declare(strict_types=1);

namespace Waaseyaa\Genealogy\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\ContentEntityBase;

#[ContentEntityType(id: 'genealogy_family')]
#[ContentEntityKeys(label: 'display_name')]
final class GenealogyFamily extends ContentEntityBase
{
    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $entityKeys
     * @param array<string, mixed> $fieldDefinitions
     */
    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        $values += [
            'status' => 0,
        ];

        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }
}
