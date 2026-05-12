<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Backend;

use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\EntityStorage\Query\EntityQuery;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;

/**
 * @api
 *
 * The `sql-column` field storage backend (T025, T028).
 *
 * Persists each FieldDefinition in a dedicated SQL column on the entity table.
 * Schema is managed by {@see SqlColumnSchemaBuilder}. Queries are translated
 * by {@see SqlColumnQueryTranslator}.
 *
 * ## Storage contract
 *
 * - Every registered field gets its own column; no `_data` blob is used.
 * - `json`-typed fields are JSON-encoded before write and decoded on read
 *   (same convention as SqlBlobBackend for consistency).
 * - `bool` fields are stored as INTEGER 0/1 in SQLite; coerced back to bool
 *   on read.
 * - `datetime` fields stored as TEXT (ISO 8601) in SQLite; returned as-is.
 * - `decimal` fields stored as TEXT in SQLite for lossless round-trip.
 *
 * ## Query contract (FR-014, FR-015)
 *
 * `supportsQuery()` returns true for fields whose type maps to a known §8.2
 * column type AND the query's operator (if extractable) is supported by the
 * {@see SqlColumnQueryTranslator}. Because EntityQuery is still a marker
 * interface (WP06 enriches it), supportsQuery() returns true for all non-
 * vector fields when the query object cannot be inspected further.
 *
 * ## Construction
 *
 * This backend requires a DBALDatabase (not DatabaseInterface) so it can
 * call getConnection() for platform detection in the schema builder.
 * Construct via the framework provider or BackendRegistrar, not directly.
 *
 * @internal Only instantiate via the framework provider; use BackendRegistrar to obtain.
 */
final class SqlColumnBackend implements FieldStorageBackendInterface
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly DBALDatabase $database,
        private readonly string $entityTableName,
        private readonly string $idKey,
        private readonly string $entityTypeId,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        // TODO(WP06): inject SqlColumnQueryTranslator once EntityQuery exposes operators.
    }

    public function id(): string
    {
        return ReservedBackendIds::SQL_COLUMN;
    }

    /**
     * Read a single field value from the entity's column.
     *
     * Returns null when the entity does not exist or the field column is absent.
     * Performs type coercion on read (json decoded, bool cast).
     */
    public function read(EntityInterface $entity, FieldDefinition $field): mixed
    {
        $id = $entity->id();
        if ($id === null) {
            return null;
        }

        $fieldName = $field->getName();

        $result = $this->database->select($this->entityTableName)
            ->fields($this->entityTableName, [$fieldName])
            ->condition($this->idKey, $id)
            ->execute();

        foreach ($result as $row) {
            $row = (array) $row;
            $value = $row[$fieldName] ?? null;

            return $this->coerceOnRead($field, $value);
        }

        return null;
    }

    /**
     * Write a single field value to the entity's column.
     *
     * Idempotent: calling write() twice with the same value produces the
     * same stored state as calling it once.
     */
    public function write(EntityInterface $entity, FieldDefinition $field, mixed $value): void
    {
        $id = $entity->id();
        if ($id === null) {
            return;
        }

        $fieldName = $field->getName();
        $coerced = $this->coerceOnWrite($field, $value);

        $this->database->update($this->entityTableName)
            ->fields([$fieldName => $coerced])
            ->condition($this->idKey, $id)
            ->execute();
    }

    /**
     * Delete all column values this backend holds for an entity.
     *
     * Issues a DELETE statement on the entity row. When called through the
     * coordinator, the coordinator may also issue its own row-level DELETE —
     * the second call is a no-op because the row no longer exists (idempotent).
     *
     * `read()` after `delete()` returns null because the row is gone.
     */
    public function delete(EntityInterface $entity): void
    {
        $id = $entity->id();
        if ($id === null) {
            return;
        }

        $this->database->delete($this->entityTableName)
            ->condition($this->idKey, $id)
            ->execute();
    }

    /**
     * {@inheritdoc}
     *
     * sql-column returns true for all fields whose type maps to a known §8.2
     * column type. float_vector_<n> is rejected (must route to vector backend).
     *
     * Because EntityQuery is a marker interface until WP06, we cannot inspect
     * the operator set at this call site. We return true for all non-vector
     * field types, matching FR-014 ("MUST report supportsQuery(): true for all
     * stored field types").
     */
    public function supportsQuery(FieldDefinition $field, EntityQuery $query): bool
    {
        $fieldType = strtolower($field->getType());

        // float_vector_<n> is forbidden in this backend.
        if (preg_match('/^float_vector_\d+$/', $fieldType)) {
            return false;
        }

        // Fields explicitly routed elsewhere are not ours to query.
        $backendId = $field->getBackendId();
        if ($backendId !== null && $backendId !== ReservedBackendIds::SQL_COLUMN) {
            return false;
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Coerce a value for storage (write path).
     */
    private function coerceOnWrite(FieldDefinition $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = strtolower($field->getType());

        return match ($type) {
            'json' => is_string($value) ? $value : json_encode($value, \JSON_THROW_ON_ERROR),
            'bool', 'boolean' => (int) ((bool) $value),
            default => $value,
        };
    }

    /**
     * Coerce a value after read (read path).
     */
    private function coerceOnRead(FieldDefinition $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = strtolower($field->getType());

        return match ($type) {
            'json' => $this->decodeJson($value, $field->getName()),
            'bool', 'boolean' => (bool) $value,
            default => $value,
        };
    }

    /**
     * JSON-decode a column value with error logging.
     */
    private function decodeJson(mixed $value, string $fieldName): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        try {
            return json_decode($value, associative: true, depth: 512, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->warning(sprintf(
                'Corrupt JSON in sql-column field "%s" for %s entity: %s',
                $fieldName,
                $this->entityTypeId,
                $e->getMessage(),
            ));
            return null;
        }
    }
}
