<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage;

use Psr\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\RevisionableEntityInterface;
use Waaseyaa\EntityStorage\Event\AfterSaveEvent;
use Waaseyaa\EntityStorage\Event\BeforeSaveEvent;

/**
 * Revision storage for entity types using the `sql-blob` primary backend.
 *
 * Reads and writes the `<entity>__revision` table where all non-key data is
 * stored as a `_data` JSON blob — byte-identical to the primary table's blob
 * encoding (T015 discipline: same `JSON_THROW_ON_ERROR`-only flags, no extras).
 *
 * ## Code-sharing approach
 *
 * Both this class and {@see RevisionableSqlColumnStorage} are `final` and
 * delegate to {@see RevisionRowHydrator} via **composition**. A shared base
 * class was rejected because the two classes have different write paths
 * (blob vs. per-column), and PHP traits would obscure the dependency graph.
 * Composition keeps each class self-contained and independently testable.
 *
 * @api
 */
final class RevisionableSqlBlobStorage implements RevisionableEntityStorageInterface
{
    private readonly RevisionRowHydrator $hydrator;

    public function __construct(
        private readonly DBALDatabase $database,
        EntityTypeInterface $entityType,
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {
        $this->hydrator = new RevisionRowHydrator($database, $entityType);
    }

    /**
     * {@inheritdoc}
     */
    public function loadRevision(
        EntityTypeInterface $type,
        int|string $revisionId,
    ): ?RevisionableEntityInterface {
        $revisionTable = $this->hydrator->revisionTableName();

        $result = $this->database
            ->select($revisionTable)
            ->fields($revisionTable)
            ->condition('vid', $revisionId)
            ->execute();

        $row = null;
        foreach ($result as $r) {
            $row = (array) $r;
            break;
        }

        if ($row === null) {
            return null;
        }

        // Need the primary table's current vid to determine isCurrentRevision.
        $idColumn = $this->hydrator->idColumn();
        $fkEntityId = $row[$idColumn] ?? null;
        $currentVid = $fkEntityId !== null
            ? $this->hydrator->fetchCurrentVid($fkEntityId)
            : null;

        return $this->hydrator->hydrateRevisionRow(
            row: $row,
            currentVid: $currentVid ?? -1,
            isBlob: true,
        );
    }

    /**
     * {@inheritdoc}
     *
     * Generator — yields in descending `revision_created_at` order.
     *
     * @return \Generator<RevisionableEntityInterface>
     */
    public function listRevisions(RevisionableEntityInterface $entity): iterable
    {
        $revisionTable = $this->hydrator->revisionTableName();
        $idColumn = $this->hydrator->idColumn();
        $entityId = $entity->id();

        if ($entityId === null) {
            return;
        }

        $currentVid = $this->hydrator->fetchCurrentVid($entityId);

        $rows = $this->database->query(
            'SELECT * FROM ' . $this->database->quoteIdentifier($revisionTable)
            . ' WHERE ' . $this->database->quoteIdentifier($idColumn) . ' = ?'
            . ' ORDER BY revision_created_at DESC',
            [$entityId],
        );

        foreach ($rows as $row) {
            yield $this->hydrator->hydrateRevisionRow(
                row: (array) $row,
                currentVid: $currentVid ?? -1,
                isBlob: true,
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setCurrentRevision(
        RevisionableEntityInterface $entity,
        int|string $revisionId,
    ): void {
        $revisionTable = $this->hydrator->revisionTableName();
        $primaryTable = $this->hydrator->primaryTableName();
        $idColumn = $this->hydrator->idColumn();
        $entityId = $entity->id();

        if ($entityId === null) {
            throw new \InvalidArgumentException('Cannot set current revision for an entity without an id.');
        }

        // Verify the target revision exists.
        $result = $this->database
            ->select($revisionTable)
            ->fields($revisionTable, ['vid'])
            ->condition('vid', $revisionId)
            ->execute();

        $found = false;
        foreach ($result as $row) {
            $row = (array) $row;
            if ((int) ($row['vid'] ?? -1) === (int) $revisionId) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new \InvalidArgumentException(sprintf(
                'Revision "%s" does not exist in table "%s".',
                $revisionId,
                $revisionTable,
            ));
        }

        $saveContext = SaveContext::default();

        if ($this->dispatcher !== null) {
            $this->dispatcher->dispatch(new BeforeSaveEvent($entity, $saveContext, false));
        }

        $transaction = $this->database->transaction();
        try {
            $this->database->update($primaryTable)
                ->fields(['vid' => (int) $revisionId])
                ->condition($idColumn, $entityId)
                ->execute();
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        if ($this->dispatcher !== null) {
            $this->dispatcher->dispatch(new AfterSaveEvent($entity, $saveContext, false));
        }
    }
}
