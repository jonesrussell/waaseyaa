<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Backend;

use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\EntityStorage\Query\EntityQuery;
use Waaseyaa\Field\FieldDefinition;

/**
 * @api
 *
 * The single per-field storage strategy contract.
 *
 * Implementations are registered via {@see HasFieldStorageBackendsInterface}.
 * The id namespace is partitioned: `sql-blob`, `sql-column`, and `vector` are
 * reserved by the framework. Apps and packages may register under any
 * non-reserved id.
 *
 * Backends are responsible for storing field values; they do NOT dispatch
 * entity lifecycle events — the coordinator (WP02) owns that.
 */
interface FieldStorageBackendInterface
{
    /**
     * Stable backend id.
     *
     * Reserved ids: sql-blob, sql-column, vector.
     * Third-party ids MUST NOT use reserved ids or collide with other registered backends.
     */
    public function id(): string;

    /**
     * Read a single field value for an entity.
     *
     * Returns null when the value is not stored by this backend.
     */
    public function read(EntityInterface $entity, FieldDefinition $field): mixed;

    /**
     * Write a single field value for an entity.
     *
     * MUST be idempotent on the backend's view: calling write() twice with the
     * same value MUST produce the same stored state as calling it once.
     */
    public function write(EntityInterface $entity, FieldDefinition $field, mixed $value): void;

    /**
     * Delete all values this backend holds for an entity.
     *
     * Cascades across all fields the backend owns for this entity.
     * Called when the entity itself is deleted.
     */
    public function delete(EntityInterface $entity): void;

    /**
     * Declare whether this backend can satisfy a given query against a given field.
     *
     * MUST be called at definition-validation time, not at query time. Backends
     * that cannot satisfy a query MUST return false; callers MUST then throw
     * {@see \Waaseyaa\EntityStorage\Exception\UnsupportedQueryException} with a
     * precise reason at definition-validation time, not at query time.
     */
    public function supportsQuery(FieldDefinition $field, EntityQuery $query): bool;
}
