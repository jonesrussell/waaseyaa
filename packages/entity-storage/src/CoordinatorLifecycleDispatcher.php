<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage;

use Psr\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\EntityStorage\Backend\BackendRegistrar;
use Waaseyaa\EntityStorage\Backend\FieldStorageBackendInterface;
use Waaseyaa\EntityStorage\Event\AbortOperationException;
use Waaseyaa\EntityStorage\Event\AfterDeleteEvent;
use Waaseyaa\EntityStorage\Event\AfterSaveEvent;
use Waaseyaa\EntityStorage\Event\BeforeDeleteEvent;
use Waaseyaa\EntityStorage\Event\BeforeSaveEvent;
use Waaseyaa\EntityStorage\Exception\PartialSaveException;
use Waaseyaa\EntityStorage\Exception\UnknownBackendException;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\LogLevel;
use Waaseyaa\Foundation\Log\NullLogger;

/**
 * @api
 *
 * Wraps the coordinator's backend fan-out with lifecycle event dispatch and
 * partial-save error semantics.
 *
 * ## Save flow
 * 1. Dispatch {@see BeforeSaveEvent} — abort if subscriber throws {@see AbortOperationException}.
 * 2. Execute backend writes (primary first, alternates in registration order).
 *    - Track committed backend ids as each write completes.
 *    - On any {@see \Throwable}: throw {@see PartialSaveException}; no {@see AfterSaveEvent}.
 * 3. Dispatch {@see AfterSaveEvent} only after all writes succeed.
 *
 * ## Delete flow
 * Symmetric: {@see BeforeDeleteEvent} → fan-out → {@see AfterDeleteEvent} (on success only).
 *
 * ## No dispatcher
 * When `$dispatcher` is null the fan-out executes identically to WP02 behaviour
 * (no events, no partial-save tracking beyond exception throw).
 * PartialSaveException is still thrown on backend failure regardless.
 *
 * @see EntityStorageCoordinator
 */
final class CoordinatorLifecycleDispatcher
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly BackendRegistrar $registrar,
        private readonly ?EventDispatcherInterface $dispatcher,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Execute a save fan-out with lifecycle event dispatch.
     *
     * @param array<string, list<FieldDefinition>> $groups     Backend id → fields.
     * @param string                               $primaryId  Primary backend id (written first).
     *
     * @throws AbortOperationException   When a BeforeSaveEvent subscriber aborts.
     * @throws PartialSaveException      When one or more backends fail mid-fan-out.
     * @throws UnknownBackendException   When a field references an unregistered backend.
     */
    public function save(
        EntityInterface $entity,
        EntityTypeInterface $entityType,
        array $groups,
        string $primaryId,
        SaveContext $saveContext,
        bool $isNewRevision,
    ): void {
        $startMs = (int) (microtime(true) * 1000);

        if ($this->dispatcher !== null) {
            $this->dispatcher->dispatch(new BeforeSaveEvent($entity, $saveContext, $isNewRevision));
        }

        /** @var string[] $committed */
        $committed = [];
        // Build an ordered list: primary first, then alternates in registration order.
        $ordered = $this->buildOrderedBackendIds($groups, $primaryId);

        try {
            foreach ($ordered as $backendId) {
                $backend = $this->requireBackend($backendId, $entityType);
                $fields = $groups[$backendId] ?? [];

                foreach ($fields as $field) {
                    $backend->write($entity, $field, $entity->get($field->getName()));
                }

                $committed[] = $backendId;
            }
        } catch (UnknownBackendException $e) {
            // Config error — propagate directly; do not wrap as partial save.
            throw $e;
        } catch (\Throwable $e) {
            $uncommitted = array_values(array_diff($ordered, $committed));

            $this->logPartialSave(
                entity: $entity,
                entityType: $entityType,
                committed: $committed,
                uncommitted: $uncommitted,
                cause: $e,
                startMs: $startMs,
            );

            throw new PartialSaveException(
                entity: $entity,
                causedBy: $e,
                committedBackends: $committed,
                uncommittedBackends: $uncommitted,
            );
        }

        if ($this->dispatcher !== null) {
            $this->dispatcher->dispatch(new AfterSaveEvent($entity, $saveContext, $isNewRevision));
        }

        $this->logLifecycle(
            event: 'save',
            entity: $entity,
            entityType: $entityType,
            outcome: 'ok',
            startMs: $startMs,
        );
    }

    /**
     * Execute a delete fan-out with lifecycle event dispatch.
     *
     * @param array<string, list<FieldDefinition>> $groups Backend id → fields.
     *
     * @throws AbortOperationException  When a BeforeDeleteEvent subscriber aborts.
     * @throws PartialSaveException     When one or more backends fail mid-fan-out.
     * @throws UnknownBackendException  When a field references an unregistered backend.
     */
    public function delete(
        EntityInterface $entity,
        EntityTypeInterface $entityType,
        array $groups,
    ): void {
        $startMs = (int) (microtime(true) * 1000);

        if ($this->dispatcher !== null) {
            $this->dispatcher->dispatch(new BeforeDeleteEvent($entity));
        }

        /** @var string[] $committed */
        $committed = [];
        $ordered = array_keys($groups);

        try {
            foreach ($ordered as $backendId) {
                /** @var string $backendId */
                $backend = $this->requireBackend($backendId, $entityType);
                $backend->delete($entity);
                $committed[] = $backendId;
            }
        } catch (UnknownBackendException $e) {
            // Config error — propagate directly; do not wrap as partial save.
            throw $e;
        } catch (\Throwable $e) {
            $uncommitted = array_values(array_diff($ordered, $committed));

            $this->logPartialSave(
                entity: $entity,
                entityType: $entityType,
                committed: $committed,
                uncommitted: $uncommitted,
                cause: $e,
                startMs: $startMs,
            );

            throw new PartialSaveException(
                entity: $entity,
                causedBy: $e,
                committedBackends: $committed,
                uncommittedBackends: $uncommitted,
            );
        }

        if ($this->dispatcher !== null) {
            $this->dispatcher->dispatch(new AfterDeleteEvent($entity));
        }

        $this->logLifecycle(
            event: 'delete',
            entity: $entity,
            entityType: $entityType,
            outcome: 'ok',
            startMs: $startMs,
        );
    }

    /**
     * Build an ordered list of backend ids for save fan-out.
     *
     * Primary backend comes first; alternates follow in the order they appear
     * in $groups (which preserves registration order per WP02 contract).
     *
     * @param array<string, list<FieldDefinition>> $groups
     * @return string[]
     */
    private function buildOrderedBackendIds(array $groups, string $primaryId): array
    {
        $ids = [];

        if (isset($groups[$primaryId])) {
            $ids[] = $primaryId;
        }

        foreach (array_keys($groups) as $id) {
            if ($id !== $primaryId) {
                $ids[] = $id;
            }
        }

        return $ids;
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

    /**
     * @param string[] $committed
     * @param string[] $uncommitted
     */
    private function logPartialSave(
        EntityInterface $entity,
        EntityTypeInterface $entityType,
        array $committed,
        array $uncommitted,
        \Throwable $cause,
        int $startMs,
    ): void {
        $durationMs = (int) (microtime(true) * 1000) - $startMs;

        $this->logger->log(LogLevel::ERROR, 'entity.lifecycle: partial save', [
            'channel' => 'entity.lifecycle',
            'outcome' => 'partial_save',
            'entity_type_id' => $entityType->id(),
            'entity_id' => $entity->id(),
            'committed_backends' => $committed,
            'uncommitted_backends' => $uncommitted,
            'cause_class' => $cause::class,
            'cause_message' => $cause->getMessage(),
            'duration_ms' => $durationMs,
        ]);
    }

    private function logLifecycle(
        string $event,
        EntityInterface $entity,
        EntityTypeInterface $entityType,
        string $outcome,
        int $startMs,
    ): void {
        $durationMs = (int) (microtime(true) * 1000) - $startMs;

        $this->logger->log(LogLevel::INFO, 'entity.lifecycle: ' . $event, [
            'channel' => 'entity.lifecycle',
            'event' => $event,
            'outcome' => $outcome,
            'entity_type_id' => $entityType->id(),
            'entity_id' => $entity->id(),
            'duration_ms' => $durationMs,
        ]);
    }
}
