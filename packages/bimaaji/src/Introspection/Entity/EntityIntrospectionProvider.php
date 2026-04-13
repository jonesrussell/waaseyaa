<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Introspection\Entity;

use Waaseyaa\Bimaaji\Graph\GraphSection;
use Waaseyaa\Bimaaji\Graph\GraphSectionProviderInterface;
use Waaseyaa\Entity\EntityTypeManagerInterface;

final class EntityIntrospectionProvider implements GraphSectionProviderInterface
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    public function getKey(): string
    {
        return 'entities';
    }

    public function provide(): GraphSection
    {
        $data = [];

        foreach ($this->entityTypeManager->getDefinitions() as $id => $entityType) {
            $data[$id] = [
                'label' => $entityType->getLabel(),
                'class' => $entityType->getClass(),
                'keys' => $entityType->getKeys(),
                'fields' => $entityType->getFieldDefinitions(),
                'group' => $entityType->getGroup(),
                'description' => $entityType->getDescription(),
                'revisionable' => $entityType->isRevisionable(),
                'translatable' => $entityType->isTranslatable(),
            ];
        }

        return new GraphSection(
            key: 'entities',
            version: '1.0',
            data: $data,
        );
    }
}
