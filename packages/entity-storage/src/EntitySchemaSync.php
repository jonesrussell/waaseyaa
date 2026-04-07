<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage;

use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityTypeInterface;

/**
 * Materializes entity-storage tables for a list of entity types.
 *
 * Thin wrapper around SqlSchemaHandler::ensureTable() that lets migrations
 * (or install commands) sync schemas for many entity types at once without
 * each caller repeating the construction boilerplate.
 */
final class EntitySchemaSync
{
    public function __construct(
        private readonly DatabaseInterface $database,
    ) {}

    /**
     * @param iterable<EntityTypeInterface> $entityTypes
     */
    public function syncAll(iterable $entityTypes): void
    {
        foreach ($entityTypes as $entityType) {
            $handler = new SqlSchemaHandler($entityType, $this->database);
            $handler->ensureTable();
            if ($entityType->isTranslatable()) {
                $handler->ensureTranslationTable();
            }
            if ($entityType->isRevisionable()) {
                $handler->ensureRevisionTable();
            }
        }
    }
}
