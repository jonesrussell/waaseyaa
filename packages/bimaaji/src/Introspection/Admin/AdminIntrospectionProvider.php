<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Introspection\Admin;

use Waaseyaa\Bimaaji\Graph\GraphSection;
use Waaseyaa\Bimaaji\Graph\GraphSectionProviderInterface;
use Waaseyaa\Entity\EntityTypeManagerInterface;

final class AdminIntrospectionProvider implements GraphSectionProviderInterface
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    public function getKey(): string
    {
        return 'admin';
    }

    public function provide(): GraphSection
    {
        $data = [];

        foreach ($this->entityTypeManager->getDefinitions() as $entityType) {
            $group = $entityType->getGroup();

            if ($group === null) {
                continue;
            }

            $keys = $entityType->getKeys();
            $isContentEntity = isset($keys['uuid']);

            $data[$entityType->id()] = [
                'label' => $entityType->getLabel(),
                'group' => $group,
                'description' => $entityType->getDescription(),
                'fields' => array_keys($entityType->getFieldDefinitions()),
                'capabilities' => [
                    'list' => isset($keys['id']),
                    'get' => isset($keys['id']),
                    'create' => $isContentEntity,
                    'update' => $isContentEntity,
                    'delete' => $isContentEntity,
                ],
            ];
        }

        return new GraphSection(
            key: 'admin',
            version: '1.0',
            data: $data,
        );
    }
}
