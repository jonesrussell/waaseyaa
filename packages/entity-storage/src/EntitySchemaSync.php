<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage;

use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\EntityStorage\Backend\ReservedBackendIds;
use Waaseyaa\EntityStorage\Schema\TranslationSchemaHandler;
use Waaseyaa\Field\FieldDefinition;

/**
 * Materializes entity-storage tables for a list of entity types.
 *
 * Thin wrapper around SqlSchemaHandler::ensureTable() that lets migrations
 * (or install commands) sync schemas for many entity types at once without
 * each caller repeating the construction boilerplate.
 *
 * Translatable storage layout (FR-020..FR-025):
 *   - `sql-blob` translatable types fold per-langcode rows directly into the
 *     base table (PK widened to (entity_id, langcode), `default_langcode`
 *     column, partial UNIQUE on uuid). No companion `_translations` table is
 *     materialized — the base table IS the translation surface.
 *   - `sql-column` translatable types (WP05) keep a `<table>__translation`
 *     side-table for translatable columns; non-translatable columns stay on
 *     the base table. WP05 wires that branch.
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
            $backend = $this->resolveBackend($entityType);
            // For sql-column translatable types, the primary table carries
            // only the non-translatable subset of fields (FR-027). Translatable
            // columns live on `<table>__translation`, owned by TranslationSchemaHandler.
            $entityLevelFields = [];
            if ($backend === ReservedBackendIds::SQL_COLUMN) {
                foreach ($entityType->getFieldDefinitions() as $name => $definition) {
                    if (!$definition instanceof FieldDefinition) {
                        continue;
                    }
                    if ($entityType->isTranslatable() && $definition->isTranslatable()) {
                        continue;
                    }
                    $entityLevelFields[$name] = $definition;
                }
            }
            $handler = new SqlSchemaHandler(
                entityType: $entityType,
                database: $this->database,
                primaryBackendId: $backend,
                entityLevelFields: $entityLevelFields,
            );
            $handler->ensureTable();

            // sql-blob translatable: per-langcode rows live IN the base table
            // (FR-020). No side-table is materialised.
            // sql-column translatable: WP05's TranslationSchemaHandler owns
            // the `<table>__translation` sibling table (FR-026..FR-031).
            // Any other (non-sql-column, non-sql-blob) backend still uses the
            // legacy `<table>_translations` shape via SqlSchemaHandler.
            if ($entityType->isTranslatable()) {
                if ($backend === ReservedBackendIds::SQL_COLUMN) {
                    $translationHandler = new TranslationSchemaHandler($this->database);
                    $translationHandler->sync($entityType);
                } elseif ($backend !== ReservedBackendIds::SQL_BLOB) {
                    $handler->ensureTranslationTable();
                }
            }

            if ($entityType->isRevisionable()) {
                $handler->ensureRevisionTable();
            }
        }
    }

    /**
     * Resolve the primary storage backend id for an entity type.
     *
     * Honours the entity-type's explicit `primaryStorageBackend` declaration;
     * falls back to the framework default (`sql-blob`) when unset.
     */
    private function resolveBackend(EntityTypeInterface $entityType): string
    {
        $declared = $entityType->getPrimaryStorageBackend();
        if (\is_string($declared) && $declared !== '') {
            return $declared;
        }
        return ReservedBackendIds::SQL_BLOB;
    }
}
