<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Schema;

use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Field\FieldDefinition;

/**
 * Emits the `<entity>__revision` table schema for a revisionable entity type.
 *
 * ## When to use
 *
 * Call {@see self::build()} from `SqlSchemaHandler::ensureTable()` (or equivalent)
 * when `EntityTypeInterface::isRevisionable()` returns `true` and the revision
 * table does not yet exist.
 *
 * ## Table layout — sql-column primary
 *
 * ```
 * <entity>__revision(
 *     vid       INTEGER PRIMARY KEY,   -- autoincrement on SQLite, SERIAL on Postgres
 *     <id_col>  <pk_type>,             -- FK to primary table (soft — no ON DELETE)
 *     revision_created_at  TEXT,       -- ISO-8601 (SQLite) / TIMESTAMPTZ (Postgres)
 *     revision_author      INTEGER,    -- nullable UID, soft FK only
 *     revision_log         TEXT,       -- nullable log message
 *     -- one column per FieldDefinition (same spec as primary table)
 * )
 * ```
 *
 * ## Table layout — sql-blob primary
 *
 * ```
 * <entity>__revision(
 *     vid       INTEGER PRIMARY KEY,
 *     <id_col>  <pk_type>,
 *     revision_created_at  TEXT,
 *     revision_author      INTEGER,
 *     revision_log         TEXT,
 *     _data     TEXT,                  -- JSON blob (mirrors sql-blob primary)
 * )
 * ```
 *
 * ## Soft FK for revision_author
 *
 * `revision_author` stores the UID of the account that created the revision.
 * No `ON DELETE` cascade is emitted — revision history MUST survive user deletion.
 * This is intentional per spec §3.5 / contracts/revisionable-entity.md §3.2.
 *
 * ## Platform note
 *
 * For `vid PRIMARY KEY`, SQLite uses `INTEGER PRIMARY KEY` (implicit ROWID /
 * autoincrement). Postgres uses `SERIAL` / `BIGSERIAL`. We emit `serial` as the
 * Waaseyaa abstract type so `DBALSchema` handles the dialect difference.
 *
 * @api
 */
final class RevisionTableBuilder
{
    public function __construct(
        private readonly DBALDatabase $database,
    ) {}

    /**
     * Materialise the `<entity>__revision` table.
     *
     * Idempotent: does nothing when the table already exists.
     *
     * @param EntityTypeInterface        $entityType       The revisionable entity type.
     * @param string                     $primaryBackendId The primary backend id (`'sql-blob'`
     *                                                     or `'sql-column'`).
     * @param FieldDefinition[]          $fields           Registered field definitions for this type.
     *
     * @throws \InvalidArgumentException When `$entityType` is not revisionable.
     * @throws \InvalidArgumentException When `$entityType` has no `'id'` key.
     *
     * @api
     */
    public function build(
        EntityTypeInterface $entityType,
        string $primaryBackendId,
        array $fields = [],
    ): void {
        if (!$entityType->isRevisionable()) {
            throw new \InvalidArgumentException(\sprintf(
                'RevisionTableBuilder::build() requires a revisionable entity type; '
                . '"%s" has revisionable=false.',
                $entityType->id(),
            ));
        }

        $keys = $entityType->getKeys();
        $idColumn = $keys['id'] ?? '';
        if ($idColumn === '') {
            throw new \InvalidArgumentException(\sprintf(
                'Entity type "%s" has no "id" key; cannot emit revision table.',
                $entityType->id(),
            ));
        }

        $revisionTable = $entityType->id() . '__revision';

        if ($this->database->schema()->tableExists($revisionTable)) {
            return;
        }

        $spec = $this->buildSpec(
            primaryBackendId: $primaryBackendId,
            idColumn: $idColumn,
            fields: $fields,
        );

        $this->database->schema()->createTable($revisionTable, $spec);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build the createTable spec array for the revision table.
     *
     * @param FieldDefinition[] $fields
     * @return array<string, mixed>
     */
    private function buildSpec(
        string $primaryBackendId,
        string $idColumn,
        array $fields,
    ): array {
        // vid is always the PRIMARY KEY of the revision table.
        // We use 'serial' so DBALSchema maps to SERIAL/BIGSERIAL (Postgres) or
        // INTEGER PRIMARY KEY AUTOINCREMENT (SQLite).
        $spec = [
            'fields' => [
                'vid' => [
                    'type'     => 'serial',
                    'not null' => true,
                ],
                $idColumn => [
                    'type'     => 'int',
                    'not null' => true,
                ],
            ],
            'primary key' => ['vid'],
        ];

        // T039: revision metadata columns on every revision table.
        $spec['fields']['revision_created_at'] = [
            'type'     => 'text',
            'not null' => false,
        ];
        $spec['fields']['revision_author'] = [
            'type'     => 'int',
            'not null' => false,
            // Intentionally NO foreign key ON DELETE — soft FK only so revision
            // history survives user deletion (spec §3.5, contract §3.2).
        ];
        $spec['fields']['revision_log'] = [
            'type'     => 'text',
            'not null' => false,
        ];

        // Backend-specific field columns.
        if ($primaryBackendId === 'sql-column') {
            $this->appendColumnFields($spec, $fields);
        } else {
            // sql-blob path: store all non-key data as a JSON blob, mirroring the
            // primary table's _data column.
            $spec['fields']['_data'] = [
                'type'     => 'text',
                'not null' => false,
            ];
        }

        return $spec;
    }

    /**
     * Append one column per FieldDefinition (sql-column path).
     *
     * Uses the same type-mapping as `SqlColumnSchemaBuilder::buildColumnSpec()`
     * so that revision-table columns have the same shape as primary-table columns.
     *
     * @param array<string, mixed>  $spec   Modified in-place.
     * @param FieldDefinition[]     $fields
     */
    private function appendColumnFields(array &$spec, array $fields): void
    {
        foreach ($fields as $field) {
            $fieldName = $field->getName();
            // Skip fields explicitly routed to a different (non-sql-column) backend.
            $backendId = $field->getBackendId();
            if ($backendId !== null && $backendId !== '' && $backendId !== 'sql-column') {
                continue;
            }

            $spec['fields'][$fieldName] = $this->columnSpecForType(
                $field->getType(),
                $field->getSettings(),
            );
        }
    }

    /**
     * Map a FieldDefinition type + settings to a DBALSchema column spec array.
     *
     * Mirrors the mapping in `SqlColumnSchemaBuilder::buildColumnSpec()` so that
     * revision-table columns have the same shape as primary-table columns.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function columnSpecForType(string $fieldType, array $settings): array
    {
        $type = strtolower($fieldType);

        $abstractType = match ($type) {
            'string'             => 'varchar',
            'text'               => 'text',
            'int', 'integer'     => 'int',
            'bigint'             => 'int',
            'bool', 'boolean'    => 'boolean',
            'datetime'           => 'text',
            'json'               => 'text',
            'uuid'               => 'varchar',
            'float'              => 'float',
            'decimal', 'numeric' => 'text',
            default              => 'text',
        };

        $spec = [
            'type'     => $abstractType,
            'not null' => (bool) ($settings['not_null'] ?? false),
        ];

        if (array_key_exists('default', $settings)) {
            $spec['default'] = $settings['default'];
        }

        if ($abstractType === 'varchar') {
            $spec['length'] = isset($settings['length']) ? (int) $settings['length'] : 255;
        }

        return $spec;
    }
}
