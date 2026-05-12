<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage;

use Psr\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\EntityStorage\Backend\BackendRegistrar;
use Waaseyaa\EntityStorage\Backend\FieldStorageBackendInterface;
use Waaseyaa\EntityStorage\Event\AbortOperationException;
use Waaseyaa\EntityStorage\Exception\PartialSaveException;
use Waaseyaa\EntityStorage\Exception\UnknownBackendException;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Foundation\Log\LoggerInterface;

/**
 * @api
 *
 * Coordinates field-level storage fan-out across multiple registered backends.
 *
 * The coordinator sits between {@see \Waaseyaa\Entity\Repository\EntityRepositoryInterface}
 * and the per-backend {@see FieldStorageBackendInterface} implementations. It does NOT
 * subsume the repository — entity lifecycle (hydration, events, language fallback) remains
 * the repository's responsibility.
 *
 * ## Read
 * Groups fields by backend; reads from each backend independently; merges field values
 * back onto the entity.
 *
 * ## Write
 * Groups fields by backend; dispatches {@see Event\BeforeSaveEvent}, writes to the entity's
 * primary backend first, then alternates in registration order (spec §6.2, FR-019), then
 * dispatches {@see Event\AfterSaveEvent} on success. Throws {@see PartialSaveException} on
 * partial failure; {@see AbortOperationException} propagates from Before* subscribers.
 *
 * ## Delete
 * Dispatches {@see Event\BeforeDeleteEvent}, calls `delete()` on every backend that owns at
 * least one field for the entity type, then dispatches {@see Event\AfterDeleteEvent} on success.
 *
 * ## Events
 * Lifecycle events are dispatched via {@see CoordinatorLifecycleDispatcher}. When no
 * `$dispatcher` is provided the fan-out executes without events (WP02 behaviour preserved).
 *
 * @see \Waaseyaa\EntityStorage\BackendResolver
 * @see \Waaseyaa\EntityStorage\Backend\BackendRegistrar
 * @see \Waaseyaa\EntityStorage\CoordinatorLifecycleDispatcher
 */
final class EntityStorageCoordinator
{
    private readonly CoordinatorLifecycleDispatcher $lifecycleDispatcher;

    public function __construct(
        private readonly BackendResolver $resolver,
        private readonly BackendRegistrar $registrar,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->lifecycleDispatcher = new CoordinatorLifecycleDispatcher(
            registrar: $registrar,
            dispatcher: $dispatcher,
            logger: $logger,
        );
    }

    /**
     * Read field values for the entity from each responsible backend and merge
     * them onto the entity.
     *
     * Fields without a registered field definition on the entity type are skipped.
     *
     * @throws UnknownBackendException When a field references an unregistered backend.
     */
    public function read(EntityInterface $entity, EntityTypeInterface $entityType): EntityInterface
    {
        $groups = $this->groupFieldsByBackend($entityType);

        foreach ($groups as $backendId => $fields) {
            $backend = $this->requireBackend($backendId, $entityType);

            foreach ($fields as $field) {
                $value = $backend->read($entity, $field);
                if ($value !== null) {
                    $entity->set($field->getName(), $value);
                }
            }
        }

        return $entity;
    }

    /**
     * Write field values from the entity to each responsible backend.
     *
     * Dispatches BeforeSaveEvent / AfterSaveEvent around the fan-out.
     * Primary backend is written first; alternates follow in registration order (spec §6.2).
     *
     * ## T044 — SaveContext::withoutNewRevision
     *
     * When `$saveContext->withoutNewRevision === true` AND the entity type is revisionable,
     * the coordinator passes `isNewRevision: false` to the lifecycle dispatcher. This signals
     * that the current revision row should be updated in place rather than a new revision row
     * being inserted. The `$isNewRevision` parameter can still override this when the caller
     * has an independent reason to force one way or the other (e.g. the repository's first-save
     * path always creates a revision regardless of SaveContext).
     *
     * @throws AbortOperationException  When a BeforeSaveEvent subscriber aborts the operation.
     * @throws PartialSaveException     When one or more backends fail mid-fan-out.
     * @throws UnknownBackendException  When a field references an unregistered backend.
     */
    public function write(
        EntityInterface $entity,
        EntityTypeInterface $entityType,
        ?SaveContext $saveContext = null,
        bool $isNewRevision = true,
    ): void {
        $resolvedContext = $saveContext ?? SaveContext::default();
        $groups = $this->groupFieldsByBackend($entityType);
        $primaryId = $this->resolvePrimaryBackendId($entityType);

        // T044: honour SaveContext::withoutNewRevision when the entity type is revisionable.
        // When the caller explicitly passes $isNewRevision = false, that takes precedence.
        // When the caller uses the default (true) and withoutNewRevision is set on the context,
        // derive the effective flag from the context.
        $effectiveIsNewRevision = $isNewRevision;
        if ($resolvedContext->withoutNewRevision && $entityType->isRevisionable()) {
            $effectiveIsNewRevision = false;
        }

        $this->lifecycleDispatcher->save(
            entity: $entity,
            entityType: $entityType,
            groups: $groups,
            primaryId: $primaryId,
            saveContext: $resolvedContext,
            isNewRevision: $effectiveIsNewRevision,
        );
    }

    /**
     * Delete all backend-owned data for the entity.
     *
     * Dispatches BeforeDeleteEvent / AfterDeleteEvent around the fan-out.
     * Calls `delete()` on every backend that owns at least one field for the entity type.
     *
     * @throws AbortOperationException  When a BeforeDeleteEvent subscriber aborts the operation.
     * @throws PartialSaveException     When one or more backends fail mid-fan-out.
     * @throws UnknownBackendException  When a field references an unregistered backend.
     */
    public function delete(EntityInterface $entity, EntityTypeInterface $entityType): void
    {
        $groups = $this->groupFieldsByBackend($entityType);

        $this->lifecycleDispatcher->delete(
            entity: $entity,
            entityType: $entityType,
            groups: $groups,
        );
    }

    /**
     * Group field definitions from the entity type by their resolved backend id.
     *
     * @return array<string, list<FieldDefinition>> Backend id → list of fields.
     */
    private function groupFieldsByBackend(EntityTypeInterface $entityType): array
    {
        $groups = [];

        foreach ($entityType->getFieldDefinitions() as $field) {
            if (!($field instanceof FieldDefinition)) {
                continue;
            }

            $backendId = $this->resolver->resolveId($entityType, $field);

            if (!isset($groups[$backendId])) {
                $groups[$backendId] = [];
            }

            $groups[$backendId][] = $field;
        }

        return $groups;
    }

    /**
     * Resolve the primary backend id for the entity type.
     *
     * This is the backend that must be written first on write operations (spec §6.2).
     */
    private function resolvePrimaryBackendId(EntityTypeInterface $entityType): string
    {
        // WP07 added getPrimaryStorageBackend() to EntityTypeInterface.
        // Direct call replaces the pre-WP07 method_exists + reflection guard.
        $primary = $entityType->getPrimaryStorageBackend();
        if ($primary !== null && $primary !== '') {
            return $primary;
        }

        return \Waaseyaa\EntityStorage\Backend\ReservedBackendIds::SQL_BLOB;
    }

    /**
     * @throws UnknownBackendException
     */
    private function requireBackend(string $backendId, EntityTypeInterface $entityType): FieldStorageBackendInterface
    {
        $backend = $this->registrar->get($backendId);

        if ($backend === null) {
            throw new UnknownBackendException(
                $backendId,
                sprintf('entity type "%s" coordinator fan-out', $entityType->id()),
            );
        }

        return $backend;
    }
}
